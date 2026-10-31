<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Implements PRD §MODULE 1 "Flexible Multi-Tier Cut-off": Tier 1 (<=17:00 WIB,
 * auto-merge), Tier 2 (17:01-22:00 WIB, Ops approval required) and Tier 3
 * (same delivery day, Ops emergency override). The PRD schema (§2) has no
 * item-level "status" column, so the Tier 2 pending/approve/reject state is
 * tracked through `audit_logs` entries rather than an invented schema column.
 */
class RevisionGateService
{
    public const TIER_1 = 'TIER_1';

    public const TIER_2 = 'TIER_2';

    public const TIER_3 = 'TIER_3';

    public function __construct(
        private readonly DeliveryNoteService $deliveryNoteService,
    ) {}

    public function determineTier(Order $order, ?Carbon $now = null): string
    {
        $now = ($now ?? Carbon::now())->timezone('Asia/Jakarta');

        if ($now->isSameDay($order->delivery_date)) {
            return self::TIER_3;
        }

        $cutoffSore = $now->copy()->setTime(17, 0);

        if ($now->lessThanOrEqualTo($cutoffSore)) {
            return self::TIER_1;
        }

        return self::TIER_2;
    }

    /**
     * @param  array<int, array{item_id: int, supplier_id: int, qty_ordered: float, estimated_price: float}>  $itemsPayload
     * @return array{tier: string, order_items: Collection}
     */
    public function submitRevision(Order $order, array $itemsPayload, User $user, ?Carbon $now = null): array
    {
        $tier = $this->determineTier($order, $now);

        return DB::transaction(function () use ($order, $itemsPayload, $user, $tier) {
            $isAddon = $order->orderItems()->exists();

            $createdItems = collect($itemsPayload)->map(function (array $payload) use ($order, $isAddon) {
                return OrderItem::create([
                    'order_id' => $order->id,
                    'item_id' => $payload['item_id'],
                    'supplier_id' => $payload['supplier_id'],
                    'is_addon' => $isAddon,
                    'qty_ordered' => $payload['qty_ordered'],
                    'estimated_price' => $payload['estimated_price'],
                ]);
            });

            match ($tier) {
                self::TIER_1 => $this->autoMerge($order, $user, $createdItems),
                self::TIER_2 => $this->flagPendingApproval($order, $user, $createdItems),
                self::TIER_3 => $this->emergencyOverride($order, $user, $createdItems),
            };

            return ['tier' => $tier, 'order_items' => $createdItems];
        });
    }

    private function autoMerge(Order $order, User $user, Collection $items): void
    {
        if ($order->status === 'DRAFT') {
            $order->update(['status' => 'SUBMITTED']);
        }

        $this->log($order, $user, 'ORDER_REVISION_AUTO_MERGED', $items);

        // Early shortfall detection (see DeliveryNoteService::checkStockShortfalls):
        // these items are confirmed immediately, so check stock now rather than
        // waiting for Ops to eventually click Generate Surat Jalan.
        $this->deliveryNoteService->checkStockShortfalls($order, $user);
    }

    private function flagPendingApproval(Order $order, User $user, Collection $items): void
    {
        // Not confirmed yet — Tier 2 still needs Ops approval, which may
        // reject these items outright. Checking stock here would risk
        // drafting POs for items that never ship. See approveRevision().
        $this->log($order, $user, 'ORDER_REVISION_PENDING_APPROVAL', $items);
    }

    private function emergencyOverride(Order $order, User $user, Collection $items): void
    {
        $this->log($order, $user, 'ORDER_REVISION_EMERGENCY_OVERRIDE', $items);
        $this->deliveryNoteService->checkStockShortfalls($order, $user);
    }

    /**
     * Ops approves a Tier 2 pending-approval batch (identified by the
     * audit_logs row that flagged it).
     */
    public function approveRevision(AuditLog $pendingLog, User $opsUser): AuditLog
    {
        $resolved = $this->resolve($pendingLog, $opsUser, 'ORDER_REVISION_APPROVED');

        // Now confirmed — same early shortfall detection as Tier 1/3.
        $orderId = $pendingLog->new_data['order_id'] ?? null;
        if ($orderId) {
            $this->deliveryNoteService->checkStockShortfalls(Order::findOrFail($orderId), $opsUser);
        }

        return $resolved;
    }

    /**
     * Ops rejects a Tier 2 pending-approval batch: the order_items created
     * during that submission are deleted and the rejection is logged.
     */
    public function rejectRevision(AuditLog $pendingLog, User $opsUser): AuditLog
    {
        return DB::transaction(function () use ($pendingLog, $opsUser) {
            $itemIds = collect($pendingLog->new_data['items'] ?? [])->pluck('id');

            if (OrderItem::whereIn('id', $itemIds)->whereNotNull('surat_jalan_id')->exists()) {
                throw new \RuntimeException(
                    'Tidak bisa menolak revisi ini: sebagian item sudah terbit di Surat Jalan.'
                );
            }

            OrderItem::whereIn('id', $itemIds)->delete();

            return $this->resolve($pendingLog, $opsUser, 'ORDER_REVISION_REJECTED');
        });
    }

    /**
     * Pending Tier 2 approvals: PENDING_APPROVAL logs that have no later
     * APPROVED/REJECTED log pointing back at them via `old_data.source_log_id`.
     */
    public function pendingApprovals(): Collection
    {
        $resolvedLogIds = AuditLog::whereIn('action', ['ORDER_REVISION_APPROVED', 'ORDER_REVISION_REJECTED'])
            ->get()
            ->pluck('old_data.source_log_id')
            ->filter()
            ->values();

        return AuditLog::where('action', 'ORDER_REVISION_PENDING_APPROVAL')
            ->whereNotIn('id', $resolvedLogIds)
            ->latest()
            ->get();
    }

    private function resolve(AuditLog $pendingLog, User $opsUser, string $action): AuditLog
    {
        $orderId = $pendingLog->new_data['order_id'] ?? 'n/a';

        return AuditLog::create([
            'user_id' => $opsUser->id,
            'action' => $action,
            'description' => "Resolusi atas audit_logs#{$pendingLog->id} (order_id={$orderId})",
            'old_data' => array_merge($pendingLog->new_data ?? [], ['source_log_id' => $pendingLog->id]),
        ]);
    }

    private function log(Order $order, User $user, string $action, Collection $items): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => "Order #{$order->id} ({$order->order_number})",
            'new_data' => [
                'order_id' => $order->id,
                'items' => $items->map(fn (OrderItem $item) => $item->only([
                    'id', 'item_id', 'supplier_id', 'qty_ordered', 'estimated_price',
                ]))->values()->toArray(),
            ],
        ]);
    }
}
