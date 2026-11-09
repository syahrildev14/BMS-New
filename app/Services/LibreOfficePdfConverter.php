<?php

namespace App\Services;

use App\Contracts\PdfConverter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class LibreOfficePdfConverter implements PdfConverter
{
    private const TIMEOUT_SECONDS = 60;

    public function convert(string $docxPath): string
    {
        $outputDir = dirname($docxPath);

        // A profile per run: concurrent soffice processes can't share one,
        // and www-data's home directory isn't writable in the container.
        $profileDir = sys_get_temp_dir().'/bms-lo-'.Str::uuid();

        try {
            $result = Process::timeout(self::TIMEOUT_SECONDS)
                ->env(['HOME' => sys_get_temp_dir()])
                ->run([
                    'soffice',
                    '--headless',
                    '--norestore',
                    '-env:UserInstallation=file://'.$profileDir,
                    '--convert-to',
                    'pdf',
                    '--outdir',
                    $outputDir,
                    $docxPath,
                ]);
        } finally {
            File::deleteDirectory($profileDir);
        }

        $pdfPath = $outputDir.'/'.pathinfo($docxPath, PATHINFO_FILENAME).'.pdf';

        if (! $result->successful() || ! File::exists($pdfPath)) {
            throw new RuntimeException('Konversi PDF gagal: '.trim($result->errorOutput() ?: $result->output()));
        }

        return File::get($pdfPath);
    }
}
