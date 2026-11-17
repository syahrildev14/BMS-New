/**
 * Pure helpers behind <CurrencyInput>. The form keeps a RAW numeric string
 * ("15000", "15000.5") that the backend validates as `numeric`; the input
 * shows it in Indonesian format ("15.000", "15.000,5").
 *
 * Typing rules: digits only, "." is a thousands separator (ignored), ","
 * starts the decimal part (max 2 digits). Pasting "15.000,50" or "Rp 15.000"
 * therefore yields the same raw value as typing 15000,50.
 */

/** Raw numeric string from what the user typed/pasted in Indonesian format. */
export function parseRupiahInput(text: string): string {
  const cleaned = text.replace(/[^\d,]/g, '');
  const commaAt = cleaned.indexOf(',');
  const intDigits = (commaAt < 0 ? cleaned : cleaned.slice(0, commaAt)).replace(/^0+(?=\d)/, '');

  if (commaAt < 0) return intDigits;

  const decimals = cleaned
    .slice(commaAt + 1)
    .replace(/,/g, '')
    .slice(0, 2);

  return `${intDigits || '0'}.${decimals}`;
}

/** Display text for a raw numeric string; keeps a trailing "," while the user is still typing decimals. */
export function formatRupiahInput(raw: string): string {
  if (raw === '') return '';

  const [intPart, decPart] = raw.split('.');
  const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

  return decPart === undefined ? grouped : `${grouped},${decPart}`;
}

/**
 * A value coming from the server ("15000.00", 15000) as the raw string the
 * input works with: trailing zero decimals dropped so it shows "15.000".
 */
export function normalizeRupiahValue(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '';

  const num = Number(value);
  if (!Number.isFinite(num)) return '';

  return String(Math.round(num * 100) / 100);
}

/** How many digits/commas sit before `caret` — the anchor used to restore the caret after reformatting. */
export function countSignificantBefore(text: string, caret: number): number {
  return text.slice(0, caret).replace(/[^\d,]/g, '').length;
}

/** Caret position in `text` right after its `count`-th digit/comma. */
export function caretAfterSignificant(text: string, count: number): number {
  if (count <= 0) return 0;

  let seen = 0;
  for (let i = 0; i < text.length; i++) {
    if (/[\d,]/.test(text[i]) && ++seen === count) return i + 1;
  }

  return text.length;
}
