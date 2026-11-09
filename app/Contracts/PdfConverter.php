<?php

namespace App\Contracts;

interface PdfConverter
{
    /**
     * @return string the PDF file contents
     *
     * @throws \RuntimeException when the conversion fails
     */
    public function convert(string $docxPath): string;
}
