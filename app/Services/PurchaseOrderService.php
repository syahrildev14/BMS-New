<?php

namespace App\Services;

use App\Models\Gudang;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    /**
     * Group DRY_GOODS stock shortfalls by supplier and raise one DRAFT
     * Purchase Order per supplier for Purchasing to review before sending.
     *
     * @param  Collection<int, array{item_id: int, supplier_id: int, qty: float, unit_cost: float}>  $shortfalls
     * @return Collection<int, PurchaseOrder>
     */
    public function generateDraftsForShortfall(Order $order, Gudang $gudang, Collection $shortfalls, User $user): Collection
    {
        return DB::transaction(function () use ($order, $gudang, $shortfalls, $user) {
            return $shortfalls
                ->groupBy('supplier_id')
                ->map(function (Collection $items, int $supplierId) use ($order, $gudang, $user) {
                    $supplier = Supplier::findOrFail($supplierId);

                    $purchaseOrder = $this->createPurchaseOrderWithUniqueNumber($supplier, $gudang, $order, $user);

                    foreach ($items as $shortfall) {
                        $purchaseOrder->items()->create([
                            'item_id' => $shortfall['item_id'],
                            'qty_ordered' => $shortfall['qty'],
                            'unit_cost' => $shortfall['unit_cost'],
                        ]);
                    }

                    return $purchaseOrder;
                })
                ->values();
        });
    }

    /**
     * po_number's daily-per-supplier sequence is read-then-write — two
     * shortfalls for the same supplier detected in quick succession can both
     * read the same count() and collide on the unique po_number constraint.
     * lockForUpdate() serializes against any already-committed row for that
     * supplier/day, and the retry-on-duplicate-key loop covers the case where
     * both transactions started with zero existing rows to lock in the first
     * place (MySQL/InnoDB allows retrying a failed statement within the same
     * transaction, unlike Postgres).
     */
    private function createPurchaseOrderWithUniqueNumber(Supplier $supplier, Gudang $gudang, Order $order, User $user): PurchaseOrder
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $sequence = PurchaseOrder::where('supplier_id', $supplier->id)
                ->whereDate('created_at', now()->toDateString())
                ->lockForUpdate()
                ->count() + $attempt;
            // + $attempt (bukan + 1): nomor PO bisa diubah manual, jadi nomor
            // urut berikutnya mungkin sudah terpakai — coba nomor setelahnya.

            try {
                return PurchaseOrder::create([
                    'po_number' => sprintf('PO/%s/%s/%02d', now()->format('Ymd'), $supplier->code, $sequence),
                    'supplier_id' => $supplier->id,
                    'gudang_id' => $gudang->id,
                    'triggered_by_order_id' => $order->id,
                    'status' => 'DRAFT',
                    'created_by' => $user->id,
                ]);
            } catch (QueryException $e) {
                $isDuplicateKey = (int) $e->getCode() === 23000;

                if (! $isDuplicateKey || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor PO unik setelah beberapa percobaan.');
    }

    public function send(PurchaseOrder $purchaseOrder, User $user): PurchaseOrder
    {
        abort_unless($purchaseOrder->status === 'DRAFT', 422, 'Hanya PO berstatus DRAFT yang bisa dikirim.');

        $purchaseOrder->update([
            'status' => 'SENT',
            'sent_by' => $user->id,
            'sent_at' => now(),
        ]);

        return $purchaseOrder;
    }
}
