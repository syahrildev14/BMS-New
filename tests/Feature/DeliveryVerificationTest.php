<?php

use App\Models\DeliveryDocumentPhoto;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Services\DeliveryNoteService;
use App\Services\SplitInvoiceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleAndPermissionSeeder::class);
    makeGudang();

    $this->dapur = makeDapur();
    $this->owner = makeUserWithRole('dapur', ['dapur_id' => $this->dapur->id]);
    $this->ops = makeUserWithRole('ops');
    $this->supplier = makeSupplier();

    $item = makeItem(['category' => 'WET_GOODS', 'default_supplier_id' => $this->supplier->id]);
    $this->order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $this->line = makeOrderItem($this->order, $item, $this->supplier, ['qty_ordered' => 10, 'final_cost_price' => 900, 'final_selling_price' => 1000]);

    $this->document = app(DeliveryNoteService::class)
        ->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', User::factory()->create()->id)['documents']->first();
});

function fillStt(Order $order, OrderItem $line, User $owner, float $received = 10)
{
    return test()->actingAs($owner)->post(route('dapur.orders.stt', $order), [
        'items' => [['id' => $line->id, 'qty_received' => $received, 'qty_rejected' => 0]],
    ]);
}

it('lets the owning Dapur upload and view photos, and Ops view them', function () {
    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->image('datang.jpg')],
    ])->assertSessionHasNoErrors();

    $photo = DeliveryDocumentPhoto::firstOrFail();
    Storage::disk('local')->assertExists($photo->path);

    $this->actingAs($this->owner)->get(route('dapur.documents.photos.show', [$this->document, $photo]))->assertOk();
    $this->actingAs($this->ops)->get(route('ops.verifications.photos.show', [$this->document, $photo]))->assertOk();
});

it('blocks other Dapur from a document photos', function () {
    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->image('datang.jpg')],
    ]);
    $photo = DeliveryDocumentPhoto::firstOrFail();
    $stranger = makeUserWithRole('dapur', ['dapur_id' => makeDapur()->id]);

    $this->actingAs($stranger)->get(route('dapur.documents.photos.show', [$this->document, $photo]))->assertForbidden();
    $this->actingAs($stranger)->delete(route('dapur.documents.photos.destroy', [$this->document, $photo]))->assertForbidden();
    $this->actingAs($stranger)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->image('x.jpg')],
    ])->assertForbidden();

    expect(DeliveryDocumentPhoto::count())->toBe(1);
});

it('rejects non-image and oversized files, and more than 10 photos per document', function () {
    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');

    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->image('besar.jpg')->size(6000)],
    ])->assertSessionHasErrors('photos.0');

    $ten = array_map(fn ($i) => UploadedFile::fake()->image("f{$i}.jpg"), range(1, 10));
    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), ['photos' => $ten])->assertSessionHasNoErrors();

    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->image('ke11.jpg')],
    ])->assertSessionHasErrors('photos');

    expect(DeliveryDocumentPhoto::count())->toBe(10);
});

it('refuses verification until every line has STT', function () {
    $this->actingAs($this->ops)->post(route('ops.verifications.verify', $this->document))->assertSessionHasErrors('verification');
    expect($this->document->fresh()->verification_status)->toBe('PENDING');

    fillStt($this->order, $this->line, $this->owner)->assertSessionHasNoErrors();

    $this->actingAs($this->ops)->post(route('ops.verifications.verify', $this->document))->assertSessionHasNoErrors();

    $document = $this->document->fresh();
    expect($document->verification_status)->toBe('VERIFIED');
    expect($document->verified_by)->toBe($this->ops->id);
    expect($document->verified_at)->not->toBeNull();
});

it('requires notes for a discrepancy', function () {
    $this->actingAs($this->ops)->post(route('ops.verifications.discrepancy', $this->document), ['notes' => ''])->assertSessionHasErrors('notes');

    $this->actingAs($this->ops)->post(route('ops.verifications.discrepancy', $this->document), ['notes' => 'Kurang 2 kg'])->assertSessionHasNoErrors();

    expect($this->document->fresh()->verification_status)->toBe('DISCREPANCY');
    expect($this->document->fresh()->verification_notes)->toBe('Kurang 2 kg');
});

it('blocks invoicing until the document is verified', function () {
    fillStt($this->order, $this->line, $this->owner);

    try {
        app(SplitInvoiceService::class)->generateInvoicesForOrder($this->order);
        $this->fail('Expected the crosscheck guard to reject invoicing.');
    } catch (ValidationException $e) {
        expect($e->errors()['order_id'][0])->toContain($this->document->sj_number);
    }

    expect(Invoice::count())->toBe(0);

    $this->actingAs($this->ops)->post(route('ops.verifications.verify', $this->document));

    expect(app(SplitInvoiceService::class)->generateInvoicesForOrder($this->order))->toHaveCount(1);
});

it('exempts legacy documents without a supplier from the invoice guard', function () {
    $order = makeOrder($this->dapur, ['delivery_date' => '2030-02-01']);
    $legacy = SuratJalan::create([
        'sj_number' => 'SJ/LEGACY/1',
        'order_id' => $order->id,
        'type' => 'PRIMARY',
        'printed_by' => $this->ops->id,
    ]);
    makeOrderItem($order, makeItem(), $this->supplier, [
        'surat_jalan_id' => $legacy->id,
        'qty_received' => 10,
        'qty_rejected' => 0,
        'final_selling_price' => 1000,
    ]);

    expect(app(SplitInvoiceService::class)->generateInvoicesForOrder($order))->toHaveCount(1);
    $this->actingAs($this->ops)->get(route('ops.verifications.show', $legacy))->assertNotFound();
});

it('locks STT and photos once verified, and unlocks them when reopened', function () {
    fillStt($this->order, $this->line, $this->owner);
    $this->actingAs($this->ops)->post(route('ops.verifications.verify', $this->document));

    fillStt($this->order, $this->line, $this->owner, 8)->assertSessionHasErrors('items');
    expect((float) $this->line->fresh()->qty_received)->toBe(10.0);

    $this->actingAs($this->owner)->post(route('dapur.documents.photos.store', $this->document), [
        'photos' => [UploadedFile::fake()->image('telat.jpg')],
    ])->assertSessionHasErrors('photos');

    $this->actingAs($this->ops)->post(route('ops.verifications.discrepancy', $this->document), ['notes' => 'Revisi STT'])->assertSessionHasNoErrors();

    fillStt($this->order, $this->line, $this->owner, 8)->assertSessionHasNoErrors();
    expect((float) $this->line->fresh()->qty_received)->toBe(8.0);
});

it('voids a draft invoice when a document is reopened, and refuses once it is issued', function () {
    fillStt($this->order, $this->line, $this->owner);
    $this->actingAs($this->ops)->post(route('ops.verifications.verify', $this->document));

    $service = app(SplitInvoiceService::class);
    $invoice = $service->generateInvoicesForOrder($this->order)->first();
    expect($invoice->status)->toBe('DRAFT');

    $this->actingAs($this->ops)->post(route('ops.verifications.discrepancy', $this->document), ['notes' => 'Salah qty'])->assertSessionHasNoErrors();
    expect($invoice->fresh()->status)->toBe('VOID');

    $this->actingAs($this->ops)->post(route('ops.verifications.verify', $this->document))->assertSessionHasNoErrors();
    $invoice = $service->generateInvoicesForOrder($this->order)->first();
    $service->approveInvoice($invoice);

    $this->actingAs($this->ops)->post(route('ops.verifications.discrepancy', $this->document), ['notes' => 'Telat'])->assertSessionHasErrors('verification');
    expect($this->document->fresh()->verification_status)->toBe('VERIFIED');
});

it('requires the verification permission', function () {
    $this->actingAs($this->owner)->get(route('ops.verifications.index'))->assertForbidden();
    $this->actingAs($this->owner)->post(route('ops.verifications.verify', $this->document))->assertForbidden();
});

it('renders the crosscheck pages for Ops', function () {
    fillStt($this->order, $this->line, $this->owner);

    $this->actingAs($this->ops)->get(route('ops.verifications.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ops/verifications/index')->has('documents', 1)->where('documents.0.stt_filled_count', 1));

    $this->actingAs($this->ops)->get(route('ops.verifications.show', $this->document))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ops/verifications/show')->where('document.lines.0.qty_received', 10));
});
