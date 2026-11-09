/** Mirrors App\Services\AppearanceService, so the form can warn before saving. The server decides. */
const LIGHT_SURFACE = '#FFFFFF';
const DARK_SURFACE = '#111827';
const MIN_CONTRAST = 3;

export function isHexColor(color: string): boolean {
  return /^#[0-9a-fA-F]{6}$/.test(color);
}

function luminance(color: string): number {
  const [red, green, blue] = [1, 3, 5].map((start) => {
    const channel = parseInt(color.slice(start, start + 2), 16) / 255;
    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrast(first: string, second: string): number {
  const a = luminance(first);
  const b = luminance(second);

  return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

export function foregroundFor(color: string): string {
  return contrast(color, '#FFFFFF') >= contrast(color, '#000000') ? '#FFFFFF' : '#000000';
}

export function colorProblem(color: string): string | null {
  if (!isHexColor(color)) return 'Format warna harus #RRGGBB, misalnya #2563EB.';
  if (contrast(color, LIGHT_SURFACE) < MIN_CONTRAST) return 'Warna ini terlalu terang dan sulit terbaca di latar putih. Pilih warna yang lebih gelap.';
  if (contrast(color, DARK_SURFACE) < MIN_CONTRAST) return 'Warna ini terlalu gelap dan sulit terbaca di mode gelap. Pilih warna yang lebih terang.';

  return null;
}

/** Overrides the color written by app.blade.php for the current page load. */
export function applyThemeColor(color: string, foreground: string = foregroundFor(color)): void {
  document.documentElement.style.setProperty('--brand', color);
  document.documentElement.style.setProperty('--brand-foreground', foreground);
}
