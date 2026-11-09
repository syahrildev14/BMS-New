<?php

namespace App\Services;

use App\Contracts\PdfConverter;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\SuratJalan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Fills an uploaded .docx template and converts the result to PDF.
 */
class DocumentRenderService
{
    public function __construct(
        private readonly PdfConverter $converter,
        private readonly DocumentDataBuilder $data,
    ) {}

    public function renderDeliveryDocument(SuratJalan $document): string
    {
        return $this->render($document->documentTemplate, $this->data->forDeliveryDocument($document));
    }

    public function renderInvoice(Invoice $invoice): string
    {
        return $this->render($invoice->documentTemplate, $this->data->forInvoice($invoice));
    }

    /**
     * @param  array{anchor: string, values: array<string, string>, rows: array<int, array<string, string>>}  $data
     */
    private function render(DocumentTemplate $template, array $data): string
    {
        $workDir = sys_get_temp_dir().'/bms-render-'.Str::uuid();
        File::ensureDirectoryExists($workDir);

        try {
            $source = $workDir.'/template.docx';
            File::put($source, Storage::disk('local')->get($template->file_path));

            // Without this an "&" in a goods name produces a corrupt .docx.
            Settings::setOutputEscapingEnabled(true);

            $processor = new TemplateProcessor($source);
            $processor->cloneRowAndSetValues($data['anchor'], $data['rows']);
            $processor->setValues($data['values']);

            $filled = $workDir.'/document.docx';
            $processor->saveAs($filled);

            return $this->converter->convert($filled);
        } finally {
            File::deleteDirectory($workDir);
        }
    }
}
