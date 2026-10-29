import { useMemo, useRef, useState } from 'react';
import { formatCompactRupiah, formatRupiah } from '@/shared/lib/format';

interface TrendPoint {
  date: string;
  revenue: number;
  cogs: number;
}

const WIDTH = 760;
const HEIGHT = 240;
const PAD = { top: 16, right: 16, bottom: 28, left: 56 };

function niceTicks(max: number, count = 4): number[] {
  if (max <= 0) return [0];
  const step = Math.pow(10, Math.floor(Math.log10(max / count)));
  const normalized = max / count / step;
  const niceStep = (normalized >= 5 ? 5 : normalized >= 2 ? 2 : 1) * step;
  const ticks: number[] = [];
  for (let v = 0; v <= max + niceStep; v += niceStep) ticks.push(v);
  return ticks;
}

/**
 * Revenue vs COGS over time — 2-series line chart (categorical job: "tell
 * distinct series apart"). Crosshair + one tooltip with every series at that
 * X, per the dataviz skill's interaction spec. Falls back to a plain message
 * when there's no data instead of rendering an empty/misleading axes.
 */
export function TrendLineChart({ data }: { data: TrendPoint[] }) {
  const svgRef = useRef<SVGSVGElement>(null);
  const [hoverIndex, setHoverIndex] = useState<number | null>(null);

  const plotWidth = WIDTH - PAD.left - PAD.right;
  const plotHeight = HEIGHT - PAD.top - PAD.bottom;

  const maxValue = useMemo(() => Math.max(1, ...data.flatMap((d) => [d.revenue, d.cogs])), [data]);
  const ticks = useMemo(() => niceTicks(maxValue), [maxValue]);
  const yMax = ticks[ticks.length - 1] || 1;

  const x = (i: number) => (data.length <= 1 ? plotWidth / 2 : (i / (data.length - 1)) * plotWidth);
  const y = (v: number) => plotHeight - (v / yMax) * plotHeight;

  const linePath = (key: 'revenue' | 'cogs') => data.map((d, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(d[key])}`).join(' ');

  const handleMove = (e: React.MouseEvent<SVGSVGElement>) => {
    if (!svgRef.current || data.length === 0) return;
    const rect = svgRef.current.getBoundingClientRect();
    const relX = ((e.clientX - rect.left) / rect.width) * WIDTH - PAD.left;
    const idx = Math.round((relX / plotWidth) * (data.length - 1));
    setHoverIndex(Math.min(data.length - 1, Math.max(0, idx)));
  };

  if (data.length === 0) {
    return <p className="py-12 text-center text-sm text-gray-400">Belum ada data untuk periode/filter ini.</p>;
  }

  const hovered = hoverIndex !== null ? data[hoverIndex] : null;
  // Positions are % of the viewBox, not raw SVG units — the <svg> scales
  // responsively (w-full) while the tooltip <div> sits in real CSS pixels,
  // so only a percentage of the (same-aspect-ratio) wrapper stays correct.
  const tooltipLeftPct = hoverIndex !== null ? Math.min(((x(hoverIndex) + PAD.left + 12) / WIDTH) * 100, 78) : 0;
  const tooltipTopPct = (PAD.top / HEIGHT) * 100;

  return (
    <div className="flex flex-col gap-3">
      <div className="flex items-center gap-4 text-xs text-gray-600 dark:text-gray-300">
        <span className="flex items-center gap-1.5">
          <span className="inline-block h-0.5 w-4 rounded-full" style={{ backgroundColor: 'var(--series-revenue)' }} />
          Revenue
        </span>
        <span className="flex items-center gap-1.5">
          <span className="inline-block h-0.5 w-4 rounded-full" style={{ backgroundColor: 'var(--series-cogs)' }} />
          COGS
        </span>
      </div>

      <div className="relative">
      <svg
        ref={svgRef}
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        className="w-full"
        onMouseMove={handleMove}
        onMouseLeave={() => setHoverIndex(null)}
      >
        <g transform={`translate(${PAD.left},${PAD.top})`}>
          {ticks.map((t) => (
            <g key={t}>
              <line x1={0} x2={plotWidth} y1={y(t)} y2={y(t)} stroke="currentColor" strokeOpacity={0.1} strokeWidth={1} />
              <text x={-8} y={y(t)} textAnchor="end" dominantBaseline="middle" fontSize={10} fill="currentColor" opacity={0.5}>
                {formatCompactRupiah(t)}
              </text>
            </g>
          ))}

          {hoverIndex !== null && (
            <line x1={x(hoverIndex)} x2={x(hoverIndex)} y1={0} y2={plotHeight} stroke="currentColor" strokeOpacity={0.25} strokeWidth={1} />
          )}

          <path d={linePath('revenue')} fill="none" stroke="var(--series-revenue)" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />
          <path d={linePath('cogs')} fill="none" stroke="var(--series-cogs)" strokeWidth={2} strokeLinejoin="round" strokeLinecap="round" />

          {data.map((d, i) => (
            <g key={d.date}>
              <circle cx={x(i)} cy={y(d.revenue)} r={4} fill="var(--series-revenue)" stroke="var(--color-background)" strokeWidth={2} />
              <circle cx={x(i)} cy={y(d.cogs)} r={4} fill="var(--series-cogs)" stroke="var(--color-background)" strokeWidth={2} />
              {/* Full-height invisible hit column, wider than the dot itself. */}
              <rect
                x={x(i) - plotWidth / Math.max(data.length, 1) / 2}
                y={0}
                width={plotWidth / Math.max(data.length, 1)}
                height={plotHeight}
                fill="transparent"
                onMouseEnter={() => setHoverIndex(i)}
              />
            </g>
          ))}

          {/* Sparse x-axis labels so dense ranges don't overlap. */}
          {data.map((d, i) => {
            const step = Math.max(1, Math.ceil(data.length / 7));
            if (i % step !== 0 && i !== data.length - 1) return null;

            return (
              <text key={d.date} x={x(i)} y={plotHeight + 16} textAnchor="middle" fontSize={10} fill="currentColor" opacity={0.5}>
                {d.date.slice(5)}
              </text>
            );
          })}
        </g>
      </svg>

      {hovered && (
        <div
          className="pointer-events-none absolute rounded-md border border-white/30 bg-white/90 px-3 py-2 text-xs shadow-lg backdrop-blur-sm dark:border-white/10 dark:bg-gray-900/90"
          style={{ left: `${tooltipLeftPct}%`, top: `${tooltipTopPct}%` }}
        >
          <p className="mb-1 font-medium text-gray-500 dark:text-gray-400">{hovered.date}</p>
          <p className="flex items-center gap-1.5">
            <span className="inline-block h-0.5 w-3 rounded-full" style={{ backgroundColor: 'var(--series-revenue)' }} />
            <span className="font-semibold text-gray-900 dark:text-gray-100">{formatRupiah(hovered.revenue)}</span>
          </p>
          <p className="flex items-center gap-1.5">
            <span className="inline-block h-0.5 w-3 rounded-full" style={{ backgroundColor: 'var(--series-cogs)' }} />
            <span className="font-semibold text-gray-900 dark:text-gray-100">{formatRupiah(hovered.cogs)}</span>
          </p>
        </div>
      )}
      </div>

      <details className="text-xs">
        <summary className="cursor-pointer text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
          Lihat sebagai tabel
        </summary>
        <div className="mt-2 max-h-48 overflow-auto rounded-md border border-gray-200 dark:border-gray-800">
          <table className="w-full text-xs">
            <thead className="bg-gray-50 dark:bg-gray-800/60">
              <tr>
                <th className="px-2 py-1 text-left">Tanggal</th>
                <th className="px-2 py-1 text-right">Revenue</th>
                <th className="px-2 py-1 text-right">COGS</th>
                <th className="px-2 py-1 text-right">Margin</th>
              </tr>
            </thead>
            <tbody>
              {data.map((d) => (
                <tr key={d.date} className="border-t border-gray-100 dark:border-gray-800">
                  <td className="px-2 py-1">{d.date}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{formatRupiah(d.revenue)}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{formatRupiah(d.cogs)}</td>
                  <td className="px-2 py-1 text-right tabular-nums">{formatRupiah(d.revenue - d.cogs)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </details>
    </div>
  );
}
