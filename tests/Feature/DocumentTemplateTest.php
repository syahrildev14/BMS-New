<?php

use App\Contracts\PdfConverter;
use App\Models\Armada;
use App\Models\BankAccount;
use App\Models\DocumentTemplate;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\SuratJalan;
use App\Services\DeliveryNoteService;
use App\Services\DocumentTemplateService;
use App\Services\SplitInvoiceService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

const SJ_SCALARS = ['nomor_surat_jalan', 'sppg_name', 'tanggal', 'no_po'];
const SJ_ROW = ['item_no', 'item_nama_barang', 'item_qty', 'item_satuan', 'item_keterangan'];
const INVOICE_SCALARS = ['invoice_number', 'grand_total', 'bank_name', 'bank_account', 'bank_owner', 'invoice_date'];
const INVOICE_ROW = ['no', 'nama_barang', 'qty', 'satuan', 'harga_satuan', 'harga_total'];

function docxUpload(array $scalars, array $row): UploadedFile
{
    $word = new PhpWord;
    $section = $word->addSection();

    foreach ($scalars as $name) {
        $section->addText('${'.$name.'}');
    }

    $tableRow = $section->addTable()->addRow();

    foreach ($row as $name) {
        $tableRow->addCell(1000)->addText('${'.$name.'}');
    }

    $path = tempnam(sys_get_temp_dir(), 'docx').'.docx';
    IOFactory::createWriter($word, 'Word2007')->save($path);

    return new UploadedFile($path, 'format.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
}

function storedTemplate($test, string $type, array $scalars, array $row): DocumentTemplate
{
    return app(DocumentTemplateService::class)->store('Format', $type, docxUpload($scalars, $row), $test->admin);
}

/** Records the filled document.xml instead of running LibreOffice. */
function fakeConverter(): object
{
    $converter = new class implements PdfConverter
    {
        public ?string $xml = null;

        public bool $fail = false;

        public function convert(string $docxPath): string
        {
            if ($this->fail) {
                throw new RuntimeException('soffice mati');
            }

            $zip = new ZipArchive;
            $zip->open($docxPath);
            $this->xml = $zip->getFromName('word/document.xml');
            $zip->close();

            return '%PDF-palsu';
        }
    };

    app()->instance(PdfConverter::class, $converter);

    return $converter;
}

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RoleAndPermissionSeeder::class);
    makeGudang();
    $this->admin = makeUserWithRole('admin');
    $this->ops = makeUserWithRole('ops');
    $this->finance = makeUserWithRole('finance');
    $this->dapur = makeDapur(['name' => 'SPPG Jombatan', 'pic_phone' => '0812']);
    $this->supplier = makeSupplier();
    $this->wet = makeItem(['name' => 'Cabai & Bawang', 'category' => 'WET_GOODS', 'default_supplier_id' => $this->supplier->id]);
    $this->order = makeOrder($this->dapur, ['delivery_date' => '2030-01-15']);
    $this->line = makeOrderItem($this->order, $this->wet, $this->supplier, ['qty_ordered' => 10, 'final_cost_price' => 900, 'final_selling_price' => 1000]);
    $this->converter = fakeConverter();
    $this->documents = app(DeliveryNoteService::class);
    $this->invoices = app(SplitInvoiceService::class);
});

it('stores a valid upload and reads its placeholders', function () {
    $this->actingAs($this->admin)->post(route('admin.document-templates.store'), [
        'name' => 'SJ Koperasi',
        'document_type' => DocumentTemplate::SURAT_JALAN,
        'file' => docxUpload(SJ_SCALARS, SJ_ROW),
    ])->assertSessionHasNoErrors();

    $template = DocumentTemplate::firstOrFail();
    expect($template->placeholders)->toContain('nomor_surat_jalan', 'item_no');
    expect($template->is_active)->toBeTrue();
    Storage::disk('local')->assertExists($template->file_path);
});

it('rejects unknown placeholders, missing required ones, and non-Word files', function (array $scalars, array $row, string $fragment) {
    $this->actingAs($this->admin)->post(route('admin.document-templates.store'), [
        'name' => 'X',
        'document_type' => DocumentTemplate::SURAT_JALAN,
        'file' => docxUpload($scalars, $row),
    ])->assertSessionHasErrors(['file' => $fragment]);

    expect(DocumentTemplate::count())->toBe(0);
})->with([
    'unknown' => [[...SJ_SCALARS, 'salah_ketik'], SJ_ROW, 'Penanda tidak dikenal: salah_ketik.'],
    'missing' => [SJ_SCALARS, ['item_no'], 'Penanda wajib belum ada: item_nama_barang, item_qty, item_satuan.'],
]);

it('rejects a file that is not really a docx', function () {
    $this->actingAs($this->admin)->post(route('admin.document-templates.store'), [
        'name' => 'X',
        'document_type' => DocumentTemplate::SURAT_JALAN,
        'file' => UploadedFile::fake()->createWithContent('palsu.docx', 'bukan zip'),
    ])->assertSessionHasErrors('file');

    expect(DocumentTemplate::count())->toBe(0);
});

it('accepts the three sample formats in docs', function (string $file, string $type) {
    $path = base_path("docs/{$file}");
    $upload = new UploadedFile($path, $file, null, null, true);

    expect(app(DocumentTemplateService::class)->store('Contoh', $type, $upload, $this->admin)->placeholders)->not->toBeEmpty();
})->with([
    ['Surat Jalan.docx', DocumentTemplate::SURAT_JALAN],
    ['Surat Tanda Terima.docx', DocumentTemplate::TANDA_TERIMA],
    ['invoice.docx', DocumentTemplate::INVOICE],
])->skip(fn () => ! file_exists(base_path('docs/invoice.docx')), 'Contoh format tidak ada di docs');

it('fills every placeholder of the real sample formats', function () {
    $service = app(DocumentTemplateService::class);
    $store = fn (string $file, string $type) => $service->store('Contoh', $type, new UploadedFile(base_path("docs/{$file}"), $file, null, null, true), $this->admin);

    $tandaTerima = $store('Surat Tanda Terima.docx', DocumentTemplate::TANDA_TERIMA);
    $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id, ['document_template_id' => $tandaTerima->id]);
    expect($this->converter->xml)->toContain('Cabai &amp; Bawang')->not->toContain('${');

    $dry = makeItem(['name' => 'Beras', 'category' => 'DRY_GOODS', 'default_supplier_id' => $this->supplier->id]);
    makeStockLot(makeGudang(), $dry, 10, 1000);
    makeOrderItem($this->order, $dry, $this->supplier, ['qty_ordered' => 2]);
    $suratJalan = $store('Surat Jalan.docx', DocumentTemplate::SURAT_JALAN);
    $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->ops->id, [
        'document_template_id' => $suratJalan->id,
        'armada_id' => Armada::create(['name' => 'Truk', 'plate_number' => 'S 1 A'])->id,
        'driver_id' => Driver::create(['name' => 'Budi'])->id,
    ]);
    expect($this->converter->xml)->toContain('Beras')->toContain('S 1 A')->not->toContain('${');

    $this->order->orderItems()->update(['qty_received' => 2, 'qty_rejected' => 0, 'final_selling_price' => 1000, 'final_cost_price' => 900]);
    SuratJalan::query()->update(['verification_status' => SuratJalan::VERIFICATION_VERIFIED]);
    $invoiceTemplate = $store('invoice.docx', DocumentTemplate::INVOICE);
    $bank = BankAccount::create(['bank_name' => 'BRI', 'account_number' => '123', 'account_holder' => 'Koperasi']);
    $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $invoiceTemplate->id, 'bank_account_id' => $bank->id])->first());

    expect($this->converter->xml)->toContain('Rp 4.000')->toContain('BRI')->not->toContain('${');
})->skip(fn () => ! file_exists(base_path('docs/invoice.docx')), 'Contoh format tidak ada di docs');

it('deletes an unused template but only deactivates a used one', function () {
    $unused = storedTemplate($this, DocumentTemplate::TANDA_TERIMA, ['sppg_name'], array_slice(SJ_ROW, 0, 4));

    $this->actingAs($this->admin)->delete(route('admin.document-templates.destroy', $unused))->assertSessionHas('success');
    expect(DocumentTemplate::find($unused->id))->toBeNull();
    Storage::disk('local')->assertMissing($unused->file_path);

    $used = storedTemplate($this, DocumentTemplate::TANDA_TERIMA, ['sppg_name'], array_slice(SJ_ROW, 0, 4));
    $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id, ['document_template_id' => $used->id]);

    $this->actingAs($this->admin)->delete(route('admin.document-templates.destroy', $used))->assertSessionHas('error');
    expect(DocumentTemplate::find($used->id))->not->toBeNull();
});

it('uses the built-in PDF while no template is active, then requires a choice', function () {
    $result = $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id);
    expect($result['documents']->first()->document_template_id)->toBeNull();
    expect($this->converter->xml)->toBeNull();

    $this->line->update(['surat_jalan_id' => null]);
    storedTemplate($this, DocumentTemplate::TANDA_TERIMA, [], array_slice(SJ_ROW, 0, 4));

    expect(fn () => $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id))
        ->toThrow(ValidationException::class);
});

it('fills a delivery template, repeats its rows, and escapes special characters', function () {
    makeOrderItem($this->order, $this->wet, $this->supplier, ['qty_ordered' => 3]);
    $template = storedTemplate($this, DocumentTemplate::TANDA_TERIMA, ['sppg_name', 'tanggal', 'no_po'], array_slice(SJ_ROW, 0, 5));

    $document = $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id, ['document_template_id' => $template->id])['documents']->first();

    expect($this->converter->xml)
        ->toContain('SPPG Jombatan')
        ->toContain('15 Januari 2030')
        ->toContain($this->order->order_number)
        ->toContain('Cabai &amp; Bawang')
        ->not->toContain('${');
    expect(substr_count($this->converter->xml, 'Cabai &amp; Bawang'))->toBe(2);
    expect(Storage::disk('local')->get($document->fresh()->pdf_url))->toBe('%PDF-palsu');
});

it('requires armada and driver only when the template uses them, and keeps a snapshot', function () {
    $template = storedTemplate($this, DocumentTemplate::SURAT_JALAN, ['nomor_surat_jalan', 'armada_name', 'no_pol', 'driver_name'], array_slice(SJ_ROW, 0, 4));
    $dry = makeItem(['category' => 'DRY_GOODS', 'default_supplier_id' => $this->supplier->id]);
    makeStockLot(makeGudang(), $dry, 10, 1000);
    makeOrderItem($this->order, $dry, $this->supplier, ['qty_ordered' => 2]);
    $armada = Armada::create(['name' => 'Truk 1', 'plate_number' => 'S 1234 AB']);
    $driver = Driver::create(['name' => 'Budi']);

    expect(fn () => $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->ops->id, ['document_template_id' => $template->id]))
        ->toThrow(ValidationException::class);

    $document = $this->documents->generateDocuments($this->dapur->id, SuratJalan::SURAT_JALAN, '2030-01-15', $this->ops->id, [
        'document_template_id' => $template->id, 'armada_id' => $armada->id, 'driver_id' => $driver->id,
    ])['documents']->first();

    $armada->update(['plate_number' => 'S 9999 ZZ']);
    $driver->update(['name' => 'Andi']);

    expect($document->fresh()->plate_number)->toBe('S 1234 AB');
    expect($document->fresh()->driver_name)->toBe('Budi');
    expect($this->converter->xml)->toContain('S 1234 AB')->toContain('Budi');
});

it('rejects an inactive template, armada, or a template of another type', function () {
    $template = storedTemplate($this, DocumentTemplate::INVOICE, ['invoice_number', 'grand_total'], INVOICE_ROW);

    expect(fn () => $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id, ['document_template_id' => $template->id]))
        ->toThrow(ValidationException::class);

    $tt = storedTemplate($this, DocumentTemplate::TANDA_TERIMA, [], array_slice(SJ_ROW, 0, 4));
    $tt->update(['is_active' => false]);

    expect(fn () => $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id, ['document_template_id' => $tt->id]))
        ->toThrow(ValidationException::class);
});

function billableDocument($test): void
{
    $document = SuratJalan::create([
        'sj_number' => 'TT/'.uniqid(), 'document_type' => SuratJalan::TANDA_TERIMA, 'dapur_id' => $test->dapur->id,
        'supplier_id' => $test->supplier->id, 'shipment_date' => '2030-01-15', 'type' => 'PRIMARY', 'printed_by' => $test->ops->id,
        'verification_status' => SuratJalan::VERIFICATION_VERIFIED,
    ]);
    $test->line->update(['surat_jalan_id' => $document->id, 'qty_received' => 10, 'qty_rejected' => 2]);
    $test->order->update(['status' => 'LOCKED']);
}

it('copies the template and bank onto the invoice and renders it after approval', function () {
    billableDocument($this);
    $template = storedTemplate($this, DocumentTemplate::INVOICE, INVOICE_SCALARS, INVOICE_ROW);
    $bank = BankAccount::create(['bank_name' => 'BRI', 'account_number' => '123', 'account_holder' => 'Koperasi']);

    expect(fn () => $this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $template->id]))
        ->toThrow(ValidationException::class);

    $invoice = $this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $template->id, 'bank_account_id' => $bank->id])->first();
    $bank->update(['account_number' => '999']);
    expect($this->converter->xml)->toBeNull();

    $issued = $this->invoices->approveInvoice($invoice);

    expect($issued->bank_account)->toBe('123');
    expect($issued->issued_at)->not->toBeNull();
    expect($this->converter->xml)->toContain('BRI')->toContain('123')->toContain('Rp 8.000');
});

it('keeps rendering a draft whose template was deactivated afterwards', function () {
    billableDocument($this);
    $template = storedTemplate($this, DocumentTemplate::INVOICE, ['invoice_number', 'grand_total'], INVOICE_ROW);
    $invoice = $this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $template->id])->first();
    $template->update(['is_active' => false]);

    $this->invoices->approveInvoice($invoice);

    expect($this->converter->xml)->not->toBeNull();
});

it('refuses an additional invoice on a template without the paid placeholders', function () {
    billableDocument($this);
    $plain = storedTemplate($this, DocumentTemplate::INVOICE, ['invoice_number', 'grand_total'], INVOICE_ROW);
    $paid = $this->invoices->approveInvoice($this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $plain->id])->first());
    $paid->update(['status' => 'PAID']);

    makeOrderItem($this->order, $this->wet, $this->supplier, [
        'surat_jalan_id' => $this->line->surat_jalan_id, 'qty_ordered' => 4, 'qty_received' => 4, 'qty_rejected' => 0, 'final_selling_price' => 1000, 'final_cost_price' => 900,
    ]);

    expect(fn () => $this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $plain->id]))
        ->toThrow(ValidationException::class);
    expect(Invoice::count())->toBe(1);

    $full = storedTemplate($this, DocumentTemplate::INVOICE, ['invoice_number', 'grand_total', 'total_barang', 'sudah_dibayar'], INVOICE_ROW);
    $additional = $this->invoices->generateInvoicesForOrder($this->order, ['document_template_id' => $full->id])->first();
    $this->invoices->approveInvoice($additional);

    expect((float) $additional->fresh()->total_amount)->toBe(4000.0);
    expect($this->converter->xml)->toContain('Rp 12.000')->toContain('Rp 8.000')->toContain('Rp 4.000');
});

it('keeps the document when template rendering fails and renders on download', function () {
    $template = storedTemplate($this, DocumentTemplate::TANDA_TERIMA, [], array_slice(SJ_ROW, 0, 4));
    $this->converter->fail = true;

    $document = $this->documents->generateDocuments($this->dapur->id, SuratJalan::TANDA_TERIMA, '2030-01-15', $this->ops->id, ['document_template_id' => $template->id])['documents']->first();

    expect($document->fresh()->pdf_url)->toBeNull();
    expect($this->line->fresh()->surat_jalan_id)->toBe($document->id);

    $this->converter->fail = false;
    $this->actingAs($this->ops)->get(route('ops.delivery-notes.download', $document))->assertOk();
    expect($document->fresh()->pdf_url)->not->toBeNull();
});

it('passes the selection through the Ops and Finance forms', function () {
    $template = storedTemplate($this, DocumentTemplate::TANDA_TERIMA, [], array_slice(SJ_ROW, 0, 4));

    $this->actingAs($this->ops)->post(route('ops.delivery-notes.store'), [
        'dapur_id' => $this->dapur->id, 'document_type' => SuratJalan::TANDA_TERIMA, 'date' => '2030-01-15', 'document_template_id' => $template->id,
    ])->assertSessionHasNoErrors();

    expect(SuratJalan::firstOrFail()->document_template_id)->toBe($template->id);

    $this->line->update(['qty_received' => 10, 'qty_rejected' => 0]);
    SuratJalan::firstOrFail()->update(['verification_status' => SuratJalan::VERIFICATION_VERIFIED]);
    $invoiceTemplate = storedTemplate($this, DocumentTemplate::INVOICE, ['invoice_number', 'grand_total'], INVOICE_ROW);

    $this->actingAs($this->finance)->post(route('finance.invoices.store'), [
        'order_id' => $this->order->id, 'document_template_id' => $invoiceTemplate->id,
    ])->assertSessionHasNoErrors();

    expect(Invoice::firstOrFail()->document_template_id)->toBe($invoiceTemplate->id);
});

it('manages armada, driver and bank masters with the right permissions', function () {
    $this->actingAs($this->ops)->post(route('admin.armadas.store'), ['name' => 'Truk', 'plate_number' => 'S 1 A', 'is_active' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->ops)->post(route('admin.drivers.store'), ['name' => 'Budi', 'is_active' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->finance)->post(route('admin.bank-accounts.store'), ['bank_name' => 'BRI', 'account_number' => '1', 'account_holder' => 'K', 'is_active' => true])->assertSessionHasNoErrors();

    expect(Armada::count())->toBe(1);
    expect(Driver::count())->toBe(1);
    expect(BankAccount::count())->toBe(1);

    $this->actingAs($this->finance)->get(route('admin.armadas.index'))->assertForbidden();
    $this->actingAs($this->ops)->get(route('admin.bank-accounts.index'))->assertForbidden();
    $this->actingAs($this->ops)->get(route('admin.document-templates.index'))->assertForbidden();
    $this->actingAs($this->finance)->post(route('admin.document-templates.store'), [])->assertForbidden();
});
