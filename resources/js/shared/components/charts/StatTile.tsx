import { cn } from '@/shared/lib/utils';

interface StatTileProps {
  label: string;
  value: string;
  tone?: 'default' | 'good' | 'bad';
  hint?: string;
}

/**
 * Stat-tile contract (dataviz skill): label (sentence case, no trailing
 * colon) + value (semibold, proportional figures — never tabular-nums at
 * display size). No delta/sparkline here since these are period totals, not
 * a vs-previous-period comparison.
 */
export function StatTile({ label, value, tone = 'default', hint }: StatTileProps) {
  return (
    // min-w-0 is load-bearing: without it, a grid/flex child never shrinks
    // below its text's intrinsic width, so `truncate` below does nothing and
    // long labels/hints overflow the tile instead of clipping with an ellipsis.
    <div className="flex min-w-0 flex-col gap-1 rounded-xl border border-white/30 bg-white/10 p-4 shadow-[0_8px_32px_var(--primary-glow)] backdrop-blur-xl dark:border-white/10 dark:bg-white/5">
      <span className="truncate text-xs font-medium text-gray-500 dark:text-gray-400" title={label}>
        {label}
      </span>
      <span
        className={cn(
          'truncate text-2xl font-semibold',
          tone === 'good' && 'text-emerald-600 dark:text-emerald-400',
          tone === 'bad' && 'text-red-600 dark:text-red-400',
          tone === 'default' && 'text-gray-900 dark:text-gray-100',
        )}
        title={value}
      >
        {value}
      </span>
      {hint && (
        <span className="truncate text-xs text-gray-400" title={hint}>
          {hint}
        </span>
      )}
    </div>
  );
}
