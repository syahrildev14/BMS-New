<?php

namespace App\Support;

/**
 * Indonesian display formatters for Blade/PDF views — mirrors
 * resources/js/shared/lib/format.ts so printed documents and the web UI
 * never disagree on how a number reads.
 */
class Format
{
    public static function rupiah(float|int|string|null $value): string
    {
        $number = (float) ($value ?? 0);

        return 'Rp ' . number_format($number, 0, ',', '.');
    }

    /**
     * Trims the unnecessary ".00" from a DECIMAL(10,2) qty while keeping a
     * genuine fraction (e.g. "2,5" for 2.5 kg).
     */
    public static function qty(float|int|string|null $value): string
    {
        if ($value === null) {
            return '-';
        }

        $formatted = number_format((float) $value, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
