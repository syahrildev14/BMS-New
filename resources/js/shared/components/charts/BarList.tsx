import { formatCompactRupiah, formatRupiah } from '@/shared/lib/format';

interface SingleSeriesRow {
  label: string;
  value: number;
}

interface BarListProps {
  rows: SingleSeriesRow[];
  /** Ascending alpha steps of var(--primary) — one hue, light→dark (sequential job). */
  sequential?: boolean;
  emptyLabel?: string;
  /** Defaults to compact rupiah — pass e.g. String or formatQty for non-currency counts/quantities. */
  formatValue?: (value: number) => string;
}

const SEQUENTIAL_STEPS = [0.25, 0.4, 0.55, 0.7, 0.85];

/**
 * Horizontal bar list — magnitude comparison across an ordered or ranked set
 * of categories. Direct value label at the bar end (mandatory, not optional,
 * per the mark spec), ≤24px thick bars with a rounded data-end.
 */
export function BarList({ rows, sequential = false, emptyLabel = 'Tidak ada data.', formatValue = formatCompactRupiah }: BarListProps) {
  if (rows.length === 0) {
    return <p className="py-6 text-center text-sm text-gray-400">{emptyLabel}</p>;
  }

  const max = Math.max(1, ...rows.map((r) => r.value));

  return (
    <div className="flex flex-col gap-2.5">
      {rows.map((row, i) => {
        const pct = Math.max((row.value / max) * 100, row.value > 0 ? 2 : 0);
        const alpha = sequential ? SEQUENTIAL_STEPS[Math.min(i, SEQUENTIAL_STEPS.length - 1)] : 0.75;

        return (
          <div key={row.label} className="flex items-center gap-3" title={`${row.label}: ${formatValue(row.value)}`}>
            <span className="w-28 shrink-0 truncate text-xs text-gray-600 dark:text-gray-300">{row.label}</span>
            <div className="flex-1 rounded-full bg-gray-100 dark:bg-gray-800/60">
              <div
                className="h-5 rounded-full transition-[width]"
                style={{ width: `${pct}%`, backgroundColor: `color-mix(in srgb, var(--primary) ${alpha * 100}%, transparent)` }}
              />
            </div>
            <span className="w-20 shrink-0 text-right text-xs font-medium tabular-nums text-gray-700 dark:text-gray-200">
              {formatValue(row.value)}
            </span>
          </div>
        );
      })}
    </div>
  );
}

interface TwoSeriesRow {
  label: string;
  revenue: number;
  cogs: number;
}

/**
 * Grouped horizontal bars — revenue vs COGS per category (categorical job,
 * 2 series: comfortable without a soft cap). Legend once, direct end-labels
 * on both bars since 2 series stays well under the "label everything"
 * threshold.
 */
export function GroupedBarList({ rows, emptyLabel = 'Tidak ada data.' }: { rows: TwoSeriesRow[]; emptyLabel?: string }) {
  if (rows.length === 0) {
    return <p className="py-6 text-center text-sm text-gray-400">{emptyLabel}</p>;
  }

  const max = Math.max(1, ...rows.flatMap((r) => [r.revenue, r.cogs]));

  return (
    <div className="flex flex-col gap-4">
      <div className="flex items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
        <span className="flex items-center gap-1.5">
          <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: 'var(--series-revenue)' }} />
          Revenue
        </span>
        <span className="flex items-center gap-1.5">
          <span className="inline-block h-2.5 w-2.5 rounded-sm" style={{ backgroundColor: 'var(--series-cogs)' }} />
          COGS
        </span>
      </div>

      <div className="flex flex-col gap-3">
        {rows.map((row) => (
          <div key={row.label} className="flex flex-col gap-1">
            <span className="truncate text-xs font-medium text-gray-700 dark:text-gray-200">{row.label}</span>
            {([
              ['revenue', row.revenue, 'var(--series-revenue)'],
              ['cogs', row.cogs, 'var(--series-cogs)'],
            ] as const).map(([key, value, color]) => (
              <div key={key} className="flex items-center gap-2" title={`${row.label} — ${key}: ${formatRupiah(value)}`}>
                <div className="h-3 flex-1 rounded-full bg-gray-100 dark:bg-gray-800/60">
                  <div
                    className="h-3 rounded-full transition-[width]"
                    style={{ width: `${Math.max((value / max) * 100, value > 0 ? 1.5 : 0)}%`, backgroundColor: color }}
                  />
                </div>
                <span className="w-20 shrink-0 text-right text-xs tabular-nums text-gray-500 dark:text-gray-400">
                  {formatCompactRupiah(value)}
                </span>
              </div>
            ))}
          </div>
        ))}
      </div>
    </div>
  );
}
