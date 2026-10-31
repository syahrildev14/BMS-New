<?php

use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\RevisionGateService;
use Carbon\Carbon;

it('keeps sj_number unique and correctly sequenced across multiple Orders for the same Dapur on the same day', function () {
    makeGudang();
    $dapur = makeDapur();
    $supplier = makeSupplier();
    $wetItem = makeItem(['category' => 'WET_GOODS']);
    $opsUser = User::factory()->create();

    $revisionGate = app(RevisionGateService::class);
    $deliveryNoteService = app(DeliveryNoteService::class);
    $tier1Now = Carbon::parse('2026-11-02 10:00:00', 'Asia/Jakarta');

    $orderA = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-02-01']);
    $revisionGate->submitRevision(
        $orderA,
        [['item_id' => $wetItem->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 5, 'estimated_price' => 1000]],
        $opsUser,
        $tier1Now
    );
    $resultA = $deliveryNoteService->generateSuratJalan($orderA->id, $opsUser->id);

    $orderB = makeOrder($dapur, ['status' => 'SUBMITTED', 'delivery_date' => '2030-02-02']);
    $revisionGate->submitRevision(
        $orderB,
        [['item_id' => $wetItem->id, 'supplier_id' => $supplier->id, 'qty_ordered' => 5, 'estimated_price' => 1000]],
        $opsUser,
        $tier1Now
    );
    $resultB = $deliveryNoteService->generateSuratJalan($orderB->id, $opsUser->id);

    expect($resultA['surat_jalan']->sj_number)->not->toBe($resultB['surat_jalan']->sj_number);
    expect(SuratJalan::pluck('sj_number')->unique())->toHaveCount(2);
    expect($resultA['surat_jalan']->sj_number)->toEndWith('/01');
    expect($resultB['surat_jalan']->sj_number)->toEndWith('/02');
});
