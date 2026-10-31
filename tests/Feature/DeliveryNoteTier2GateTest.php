<?php

use App\Models\AuditLog;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\RevisionGateService;
use Carbon\Carbon;

it('does not let a Tier 2 pending-approval item slip into a generated Surat Jalan', function () {
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $wetItem = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();

    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $revisionGate = app(RevisionGateService::class);

    // Tier 1 (confirmed immediately): 10:00 WIB.
    $revisionGate->submitRevision(
        $order,
        [['item_id' => $wetItem->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 5, 'estimated_price' => 1000]],
        $opsUser,
        Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta')
    );

    // Tier 2 (needs Ops approval): 19:00 WIB same day, same order.
    $revisionGate->submitRevision(
        $order,
        [['item_id' => $wetItem->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 3, 'estimated_price' => 1000]],
        $opsUser,
        Carbon::parse('2026-11-02 19:00:00', 'Asia/Jakarta')
    );

    $order->refresh();
    expect($order->orderItems)->toHaveCount(2);

    $pendingItem = $order->orderItems->firstWhere('qty_ordered', 3.00);
    $confirmedItem = $order->orderItems->firstWhere('qty_ordered', 5.00);
    expect($pendingItem)->not->toBeNull();
    expect($confirmedItem)->not->toBeNull();

    // Ops generates the Surat Jalan before ever opening the approval page.
    $service = app(DeliveryNoteService::class);
    $result = $service->generateSuratJalan($order->id, $opsUser->id);

    expect($result['surat_jalan'])->not->toBeNull();

    $pendingItem->refresh();
    $confirmedItem->refresh();

    expect($confirmedItem->surat_jalan_id)->toBe($result['surat_jalan']->id);
    expect($pendingItem->surat_jalan_id)->toBeNull();
});

it('refuses to reject a Tier 2 revision whose items already shipped on a Surat Jalan', function () {
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();
    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $revisionGate = app(RevisionGateService::class);

    $revisionGate->submitRevision(
        $order,
        [['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 4, 'estimated_price' => 1000]],
        $opsUser,
        Carbon::parse('2026-11-02 19:00:00', 'Asia/Jakarta')
    );

    $pendingLog = AuditLog::where('action', 'ORDER_REVISION_PENDING_APPROVAL')->latest()->first();
    $orderItemId = $pendingLog->new_data['items'][0]['id'];

    // Simulate the item somehow already having shipped (corrupted/edge state).
    OrderItem::whereKey($orderItemId)->update(['surat_jalan_id' => null]);
    $suratJalan = SuratJalan::create([
        'sj_number' => 'SJ/TEST/0001',
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'sequence_number' => 1,
        'printed_by' => $opsUser->id,
    ]);
    OrderItem::whereKey($orderItemId)->update(['surat_jalan_id' => $suratJalan->id]);

    expect(fn () => $revisionGate->rejectRevision($pendingLog, $opsUser))
        ->toThrow(RuntimeException::class);

    expect(OrderItem::find($orderItemId))->not->toBeNull();
});
