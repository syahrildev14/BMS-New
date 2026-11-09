<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The system-wide primary color. Only one color is stored; the hover, soft
 * and text shades are derived from it in resources/css/app.css.
 */
class AppearanceService
{
    public const DEFAULT_COLOR = '#2563EB';

    public const PRESETS = [
        ['name' => 'Biru', 'color' => '#2563EB'],
        ['name' => 'Merah Garuda', 'color' => '#DC2626'],
        ['name' => 'Hijau', 'color' => '#15803D'],
        ['name' => 'Ungu', 'color' => '#7C3AED'],
        ['name' => 'Oranye', 'color' => '#C2410C'],
        ['name' => 'Teal', 'color' => '#0F766E'],
    ];

    private const SETTING_KEY = 'primary_color';

    private const CACHE_KEY = 'appearance.primary_color';

    /** Page and sidebar surfaces the color has to stay readable on. */
    private const LIGHT_SURFACE = '#FFFFFF';

    private const DARK_SURFACE = '#111827';

    private const MIN_CONTRAST = 3.0;

    public function primaryColor(): string
    {
        $stored = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => (string) AppSetting::where('key', self::SETTING_KEY)->value('value'),
        );

        // This value is written straight into a <style> block.
        return $this->problemWith($stored) === null ? strtoupper($stored) : self::DEFAULT_COLOR;
    }

    /**
     * @return array{color: string, foreground: string}
     */
    public function theme(): array
    {
        $color = $this->primaryColor();

        return ['color' => $color, 'foreground' => $this->foregroundFor($color)];
    }

    /**
     * White or black, whichever reads better on the color.
     */
    public function foregroundFor(string $color): string
    {
        return $this->contrast($color, '#FFFFFF') >= $this->contrast($color, '#000000') ? '#FFFFFF' : '#000000';
    }

    public function problemWith(string $color): ?string
    {
        if (preg_match('/\A#[0-9a-fA-F]{6}\z/', $color) !== 1) {
            return 'Format warna harus #RRGGBB, misalnya #2563EB.';
        }

        if ($this->contrast($color, self::LIGHT_SURFACE) < self::MIN_CONTRAST) {
            return 'Warna ini terlalu terang dan sulit terbaca di latar putih. Pilih warna yang lebih gelap.';
        }

        if ($this->contrast($color, self::DARK_SURFACE) < self::MIN_CONTRAST) {
            return 'Warna ini terlalu gelap dan sulit terbaca di mode gelap. Pilih warna yang lebih terang.';
        }

        return null;
    }

    public function update(string $color, User $user): void
    {
        $problem = $this->problemWith($color);

        if ($problem !== null) {
            throw ValidationException::withMessages(['color' => $problem]);
        }

        $color = strtoupper($color);

        DB::transaction(function () use ($color, $user) {
            $old = AppSetting::where('key', self::SETTING_KEY)->lockForUpdate()->value('value');

            AppSetting::updateOrCreate(['key' => self::SETTING_KEY], ['value' => $color]);

            $this->log('APPEARANCE_UPDATED', "Warna tema diubah menjadi {$color}.", $old, $color, $user);
        });

        Cache::forget(self::CACHE_KEY);
    }

    public function reset(User $user): void
    {
        DB::transaction(function () use ($user) {
            $setting = AppSetting::where('key', self::SETTING_KEY)->lockForUpdate()->first();

            if ($setting === null) {
                return;
            }

            $setting->delete();

            $this->log('APPEARANCE_RESET', 'Warna tema dikembalikan ke bawaan.', $setting->value, self::DEFAULT_COLOR, $user);
        });

        Cache::forget(self::CACHE_KEY);
    }

    private function log(string $action, string $description, ?string $old, string $new, User $user): void
    {
        AuditLog::create([
            'user_id' => $user->id,
            'action' => $action,
            'description' => $description,
            'old_data' => ['primary_color' => $old ?? self::DEFAULT_COLOR],
            'new_data' => ['primary_color' => $new],
        ]);
    }

    private function contrast(string $first, string $second): float
    {
        $a = $this->luminance($first);
        $b = $this->luminance($second);

        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private function luminance(string $color): float
    {
        [$red, $green, $blue] = array_map(function (string $hex) {
            $channel = hexdec($hex) / 255;

            return $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($color, 1), 2));

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }
}
