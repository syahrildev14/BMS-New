<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action',
        'description',
        'old_data',
        'new_data',
    ];

    protected function casts(): array
    {
        return [
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * order_item ids still awaiting Ops approval/rejection for a Tier 2
     * revision (RevisionGateService::flagPendingApproval) on this Order.
     * Callers that pull "unassigned" order_items for an Order (delivery note
     * generation, early shortfall detection) must exclude these — they are
     * not yet confirmed and may still be rejected (deleted) outright.
     *
     * @return Collection<int, int>
     */
    public static function pendingOrderItemIds(int $orderId): Collection
    {
        $resolvedLogIds = static::whereIn('action', ['ORDER_REVISION_APPROVED', 'ORDER_REVISION_REJECTED'])
            ->get()
            ->pluck('old_data.source_log_id')
            ->filter()
            ->values();

        return static::where('action', 'ORDER_REVISION_PENDING_APPROVAL')
            ->whereNotIn('id', $resolvedLogIds)
            ->get()
            ->filter(fn (self $log) => ($log->new_data['order_id'] ?? null) === $orderId)
            ->flatMap(fn (self $log) => collect($log->new_data['items'] ?? [])->pluck('id'))
            ->values();
    }
}
