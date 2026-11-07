<?php

use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\RevisionGateService;
use Carbon\Carbon;

it('holds back one of two same-item order lines instead of crashing when combined demand exceeds stock', function () {
    $gudang = makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $item = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $opsUser = User::factory()->create();

    makeStockLot($gudang, $item, qty: 10, unitCost: 500);

    $order = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-01-15']);

    $revisionGate = app(RevisionGateService::class);
    $tier1Now = Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta');

    // Two separate same-day Tier 1 submissions for the SAME item on the
    // SAME order — e.g. the base pre-order plus a same-morning add-on.
    $revisionGate->submitRevision(
        $order,
        [['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 6, 'estimated_price' => 1000]],
        $opsUser,
        $tier1Now
    );
    $revisionGate->submitRevision(
        $order,
        [['item_id' => $item->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 6, 'estimated_price' => 1000]],
        $opsUser,
        $tier1Now
    );

    $order->refresh();
    $rows = $order->orderItems->sortBy('id')->values();
    expect($rows)->toHaveCount(2);
    $firstRow = $rows[0];
    $secondRow = $rows[1];

    $service = app(DeliveryNoteService::class);

    // Must NOT throw — stock (10) can't cover both rows (6+6=12), but it
    // can fully cover the first one, so the SJ should still go out for that
    // row instead of failing the whole batch.
    $result = $service->generateDocuments($dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $opsUser->id);

    expect($result['documents'])->toHaveCount(1);
    expect($result['held_items'])->toHaveCount(1);

    $firstRow->refresh();
    $secondRow->refresh();

    expect($firstRow->surat_jalan_id)->toBe($result['documents']->first()->id);
    expect($secondRow->surat_jalan_id)->toBeNull();

    $lot = StockLot::where('item_id', $item->id)->first();
    expect((float) $lot->qty_remaining)->toBe(4.0);

    // Shortages no longer draft POs — they surface on the Daftar Belanja.
    expect(PurchaseOrder::count())->toBe(0);
});
