/**
 * Shared display formatters — Indonesian conventions. Only for read-only
 * display text, never for controlled <Input> values (those need the raw
 * editable numeric string, not a thousands-separated/locale string).
 */

const rupiahFormatter = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 0,
});

export function formatRupiah(value: number | string | null | undefined): string {
  const num = Number(value ?? 0);
  return rupiahFormatter.format(Number.isFinite(num) ? num : 0);
}

/**
 * Compact rupiah for stat-tile headline values — "Rp1,2Jt" / "Rp850Rb"
 * instead of a full thousands-separated figure. Full precision stays
 * available in tooltips/tables via formatRupiah; this is display-only.
 */
export function formatCompactRupiah(value: number | string | null | undefined): string {
  const num = Number(value ?? 0);
  if (!Number.isFinite(num)) return 'Rp0';

  const sign = num < 0 ? '-' : '';
  const abs = Math.abs(num);

  if (abs >= 1_000_000_000) return `${sign}Rp${(abs / 1_000_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })}M`;
  if (abs >= 1_000_000) return `${sign}Rp${(abs / 1_000_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })}Jt`;
  if (abs >= 1_000) return `${sign}Rp${(abs / 1_000).toLocaleString('id-ID', { maximumFractionDigits: 1 })}Rb`;
  return `${sign}Rp${abs.toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;
}

/**
 * Qty stored as DECIMAL(10,2) renders as "50.00"/"2.50" from the backend —
 * trims the unnecessary ".00" while keeping a genuine fraction (e.g. "2,5"
 * for 2.5 kg), using Indonesian decimal/thousands separators.
 */
export function formatQty(value: number | string | null | undefined): string {
  if (value === null || value === undefined || value === '') return '-';
  const num = Number(value);
  if (!Number.isFinite(num)) return '-';
  return num.toLocaleString('id-ID', { maximumFractionDigits: 2 });
}
