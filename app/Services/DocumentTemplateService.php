<?php

namespace App\Services;

use App\Models\Armada;
use App\Models\BankAccount;
use App\Models\DocumentTemplate;
use App\Models\Driver;
use App\Models\Invoice;
use App\Models\SuratJalan;
use App\Models\User;
use App\Support\DocumentPlaceholders;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpWord\TemplateProcessor;
use Throwable;

class DocumentTemplateService
{
    public const ADDITIONAL_INVOICE_PLACEHOLDERS = ['total_barang', 'sudah_dibayar'];

    public function store(string $name, string $documentType, UploadedFile $file, User $user): DocumentTemplate
    {
        $placeholders = $this->validatedPlaceholders($file->getRealPath(), $documentType);
        $path = $file->storeAs('document-templates', Str::uuid().'.docx', 'local');

        try {
            return DocumentTemplate::create([
                'name' => $name,
                'document_type' => $documentType,
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'placeholders' => $placeholders,
                'is_active' => true,
                'uploaded_by' => $user->id,
            ]);
        } catch (Throwable $e) {
            Storage::disk('local')->delete($path);

            throw $e;
        }
    }

    /**
     * Once used, a template stays so its documents can still be re-rendered.
     *
     * @return bool false when the template is in use and was not deleted
     */
    public function delete(DocumentTemplate $template): bool
    {
        $inUse = SuratJalan::where('document_template_id', $template->id)->exists()
            || Invoice::where('document_template_id', $template->id)->exists();

        if ($inUse) {
            return false;
        }

        $template->delete();
        Storage::disk('local')->delete($template->file_path);

        return true;
    }

    /**
     * The document columns for a Surat Jalan or Tanda Terima generation. Empty
     * while the type has no active template: the built-in PDF is used then.
     *
     * @param  array{document_template_id?: ?int, armada_id?: ?int, driver_id?: ?int}  $options
     * @return array<string, int|string>
     */
    public function deliverySelection(string $documentType, array $options): array
    {
        $template = $this->selectedTemplate($documentType, $options['document_template_id'] ?? null);

        if (! $template) {
            return [];
        }

        $attributes = ['document_template_id' => $template->id];

        if ($template->usesAny(['armada_name', 'no_pol'])) {
            $armada = $this->activeRecord(Armada::class, $options['armada_id'] ?? null, 'armada_id', 'Armada');
            $attributes += ['armada_name' => $armada->name, 'plate_number' => $armada->plate_number];
        }

        if ($template->usesAny(['driver_name'])) {
            $driver = $this->activeRecord(Driver::class, $options['driver_id'] ?? null, 'driver_id', 'Driver');
            $attributes['driver_name'] = $driver->name;
        }

        return $attributes;
    }

    /**
     * The chosen invoice template (if any) and the invoice columns it sets.
     *
     * @param  array{document_template_id?: ?int, bank_account_id?: ?int}  $options
     * @return array{template: ?DocumentTemplate, attributes: array<string, int|string|null>}
     */
    public function invoiceSelection(array $options): array
    {
        $template = $this->selectedTemplate(DocumentTemplate::INVOICE, $options['document_template_id'] ?? null);
        $attributes = ['document_template_id' => $template?->id, 'bank_name' => null, 'bank_account' => null, 'bank_owner' => null];

        if ($template?->usesAny(['bank_name', 'bank_account', 'bank_owner'])) {
            $bank = $this->activeRecord(BankAccount::class, $options['bank_account_id'] ?? null, 'bank_account_id', 'Rekening bank');
            $attributes = [
                'document_template_id' => $template->id,
                'bank_name' => $bank->bank_name,
                'bank_account' => $bank->account_number,
                'bank_owner' => $bank->account_holder,
            ];
        }

        return ['template' => $template, 'attributes' => $attributes];
    }

    private function selectedTemplate(string $documentType, ?int $templateId): ?DocumentTemplate
    {
        $active = DocumentTemplate::active()->where('document_type', $documentType);

        if ($templateId === null) {
            if ($active->exists()) {
                throw ValidationException::withMessages(['document_template_id' => 'Template dokumen wajib dipilih.']);
            }

            return null;
        }

        $template = $active->find($templateId);

        if (! $template) {
            throw ValidationException::withMessages(['document_template_id' => 'Template yang dipilih sudah tidak aktif atau tidak sesuai jenis dokumen.']);
        }

        return $template;
    }

    /**
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return TModel
     */
    private function activeRecord(string $model, ?int $id, string $field, string $label): Model
    {
        $record = $id === null ? null : $model::where('is_active', true)->find($id);

        if (! $record) {
            throw ValidationException::withMessages([$field => "{$label} wajib dipilih dan harus masih aktif."]);
        }

        return $record;
    }

    /**
     * @return array<int, string>
     */
    private function validatedPlaceholders(string $path, string $documentType): array
    {
        try {
            $placeholders = array_values(array_unique((new TemplateProcessor($path))->getVariables()));
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'File tidak bisa dibaca sebagai dokumen Word (.docx).']);
        }

        $unknown = array_diff($placeholders, DocumentPlaceholders::allowed($documentType));

        if ($unknown !== []) {
            throw ValidationException::withMessages(['file' => 'Penanda tidak dikenal: '.implode(', ', $unknown).'.']);
        }

        $missing = array_diff(DocumentPlaceholders::required($documentType), $placeholders);

        if ($missing !== []) {
            throw ValidationException::withMessages(['file' => 'Penanda wajib belum ada: '.implode(', ', $missing).'.']);
        }

        return $placeholders;
    }
}
