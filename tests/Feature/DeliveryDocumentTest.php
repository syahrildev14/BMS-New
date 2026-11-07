<?php

use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->gudang = makeGudang();
    $this->dapur = makeDapur(['code' => 'DPR-A']);
    $this->opsUser = User::factory()->create();
    $this->service = app(DeliveryNoteService::class);
});

it('combines dry goods from several daily Orders of one Dapur into one Surat Jalan per supplier', function () {
    $kopMerahPutih = makeSupplier(['code' => 'KMP']);
    $supplierB = makeSupplier(['code' => 'SUPB']);
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $kopMerahPutih->id]);
    $gula = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplierB->id]);
    makeStockLot($this->gudang, $beras, 100, 10000);
    makeStockLot($this->gudang, $gula, 100, 12000);

    $senin = makeOrder($this->dapur, ['delivery_date' => '2030-01-14']);
    $selasa = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $lineSeninBeras = makeOrderItem($senin, $beras, $kopMerahPutih, ['qty_ordered' => 5]);
    $lineSelasaBeras = makeOrderItem($selasa, $beras, $kopMerahPutih, ['qty_ordered' => 6]);
    $lineSelasaGula = makeOrderItem($selasa, $gula, $supplierB, ['qty_ordered' => 2]);

    $result = $this->service->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->opsUser->id);

    expect($result['documents'])->toHaveCount(2);

    $sjKmp = $result['documents']->firstWhere('supplier_id', $kopMerahPutih->id);
    expect($sjKmp->document_type)->toBe(SuratJalan::SURAT_JALAN);
    expect($sjKmp->dapur_id)->toBe($this->dapur->id);
    expect($sjKmp->sj_number)->toStartWith('SJ/')->toContain('/DPR-A/KMP/');
    expect($lineSeninBeras->fresh()->surat_jalan_id)->toBe($sjKmp->id);
    expect($lineSelasaBeras->fresh()->surat_jalan_id)->toBe($sjKmp->id);
    expect($lineSelasaGula->fresh()->surat_jalan_id)->not->toBe($sjKmp->id)->not->toBeNull();

    expect($senin->fresh()->status)->toBe('LOCKED');
    expect($selasa->fresh()->status)->toBe('LOCKED');
    expect($sjKmp->fresh()->pdf_url)->not->toBeNull();
    expect($senin->fresh()->suratJalans)->toHaveCount(1);
    expect($selasa->fresh()->suratJalans)->toHaveCount(2);
});

it('does not include dry goods of Orders delivering after the chosen date', function () {
    $supplier = makeSupplier();
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    makeStockLot($this->gudang, $beras, 100, 10000);

    $later = makeOrder($this->dapur, ['delivery_date' => '2030-01-20']);
    $line = makeOrderItem($later, $beras, $supplier, ['qty_ordered' => 5]);

    expect(fn () => $this->service->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->opsUser->id))
        ->toThrow(RuntimeException::class);

    expect($line->fresh()->surat_jalan_id)->toBeNull();
});

it('never puts wet goods on a Surat Jalan', function () {
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $line = makeOrderItem($order, $sayur, $supplier);

    expect(fn () => $this->service->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->opsUser->id))
        ->toThrow(RuntimeException::class);

    expect($line->fresh()->surat_jalan_id)->toBeNull();
});

it('issues a daily wet-goods Tanda Terima per supplier, only for the chosen delivery date', function () {
    $supplierA = makeSupplier(['code' => 'SAYUR']);
    $supplierB = makeSupplier(['code' => 'DAGING']);
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplierA->id]);
    $ayam = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplierB->id]);
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplierA->id]);

    $today = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $tomorrow = makeOrder($this->dapur, ['delivery_date' => '2030-01-16']);
    $lineSayur = makeOrderItem($today, $sayur, $supplierA);
    $lineAyam = makeOrderItem($today, $ayam, $supplierB);
    $lineBeras = makeOrderItem($today, $beras, $supplierA);
    $lineTomorrow = makeOrderItem($tomorrow, $sayur, $supplierA);

    $result = $this->service->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id);

    expect($result['documents'])->toHaveCount(2);
    expect($result['documents']->pluck('document_type')->unique()->all())->toBe([SuratJalan::TANDA_TERIMA]);
    expect($result['documents']->first()->sj_number)->toStartWith('TT/');
    expect($lineSayur->fresh()->surat_jalan_id)->not->toBeNull();
    expect($lineAyam->fresh()->surat_jalan_id)->not->toBeNull();
    expect($lineBeras->fresh()->surat_jalan_id)->toBeNull();
    expect($lineTomorrow->fresh()->surat_jalan_id)->toBeNull();
});

it('can generate for a single supplier only', function () {
    $supplierA = makeSupplier();
    $supplierB = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplierA->id]);
    $ayam = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplierB->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $lineA = makeOrderItem($order, $sayur, $supplierA);
    $lineB = makeOrderItem($order, $ayam, $supplierB);

    $result = $this->service->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id, $supplierA->id);

    expect($result['documents'])->toHaveCount(1);
    expect($lineA->fresh()->surat_jalan_id)->not->toBeNull();
    expect($lineB->fresh()->surat_jalan_id)->toBeNull();
});

it('holds back dry goods short on stock without drafting a PO, giving the earliest delivery date first claim', function () {
    $supplier = makeSupplier();
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    makeStockLot($this->gudang, $beras, 10, 10000);

    // Created first but delivers later: must NOT win the stock.
    $rabu = makeOrder($this->dapur, ['delivery_date' => '2030-01-16']);
    $lineRabu = makeOrderItem($rabu, $beras, $supplier, ['qty_ordered' => 8]);
    $senin = makeOrder($this->dapur, ['delivery_date' => '2030-01-14']);
    $lineSenin = makeOrderItem($senin, $beras, $supplier, ['qty_ordered' => 8]);

    $result = $this->service->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-16', $this->opsUser->id);

    expect($result['documents'])->toHaveCount(1);
    expect($lineSenin->fresh()->surat_jalan_id)->not->toBeNull();
    expect($lineRabu->fresh()->surat_jalan_id)->toBeNull();
    expect($result['held_items']->pluck('id')->all())->toBe([$lineRabu->id]);
    expect(PurchaseOrder::count())->toBe(0);
});

it('marks a second document for the same Dapur+supplier+date as ADDON and never re-ships an item', function () {
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $first = makeOrderItem($order, $sayur, $supplier);

    $resultA = $this->service->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id);

    // Calling again with nothing new must fail, not issue an empty document.
    expect(fn () => $this->service->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id))
        ->toThrow(RuntimeException::class);

    $addon = makeOrderItem($order, $sayur, $supplier, ['is_addon' => true]);
    $resultB = $this->service->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id);

    $docA = $resultA['documents']->first();
    $docB = $resultB['documents']->first();
    expect($docA->type)->toBe('PRIMARY');
    expect($docB->type)->toBe('ADDON');
    expect($docA->sj_number)->not->toBe($docB->sj_number);
    expect($first->fresh()->surat_jalan_id)->toBe($docA->id);
    expect($addon->fresh()->surat_jalan_id)->toBe($docB->id);
});

it('lets Ops generate via HTTP and lets only the owning Dapur download the document', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $ops = makeUserWithRole('ops');
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    makeOrderItem($order, $sayur, $supplier);

    $this->actingAs($ops)->post(route('ops.delivery-notes.store'), [
        'dapur_id' => $this->dapur->id,
        'document_type' => SuratJalan::TANDA_TERIMA,
        'date' => '2030-01-15',
    ])->assertSessionHasNoErrors();

    $document = SuratJalan::firstOrFail();

    $owner = makeUserWithRole('dapur', ['dapur_id' => $this->dapur->id]);
    $this->actingAs($owner)->get(route('dapur.documents.download', $document))->assertOk();

    $otherDapur = makeDapur();
    $stranger = makeUserWithRole('dapur', ['dapur_id' => $otherDapur->id]);
    $this->actingAs($stranger)->get(route('dapur.documents.download', $document))->assertForbidden();
});

it('shows pending groups per supplier on the Ops page', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $ops = makeUserWithRole('ops');
    $supplier = makeSupplier(['name' => 'KOP MERAH PUTIH']);
    $beras = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $supplier->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    makeOrderItem($order, $beras, $supplier);

    $this->actingAs($ops)
        ->get(route('ops.delivery-notes.index', ['dapur_id' => $this->dapur->id, 'document_type' => 'SURAT_JALAN', 'date' => '2030-01-15']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('ops/delivery-notes/index')
            ->has('pendingGroups', 1)
            ->where('pendingGroups.0.supplier_name', 'KOP MERAH PUTIH'));
});

it('lets Dapur record STT on items of a new document, so billing still works', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $supplier = makeSupplier();
    $sayur = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $supplier->id]);
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $line = makeOrderItem($order, $sayur, $supplier, ['qty_ordered' => 10]);

    $this->service->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->opsUser->id);

    $owner = makeUserWithRole('dapur', ['dapur_id' => $this->dapur->id]);
    $this->actingAs($owner)->post(route('dapur.orders.stt', $order), [
        'items' => [['id' => $line->id, 'qty_received' => 10, 'qty_rejected' => 1]],
    ])->assertSessionHasNoErrors();

    $line = OrderItem::findOrFail($line->id);
    expect((float) $line->qty_received)->toBe(10.0);
    expect($line->suratJalan->fresh()->tanda_terima_pdf_url)->not->toBeNull();
});
