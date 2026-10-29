import { router } from '@inertiajs/react';
import { BarList, GroupedBarList } from '@/shared/components/charts/BarList';
import { StatTile } from '@/shared/components/charts/StatTile';
import { TrendLineChart } from '@/shared/components/charts/TrendLineChart';
import { Button } from '@/shared/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/shared/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { formatRupiah } from '@/shared/lib/format';
import { cn } from '@/shared/lib/utils';
import type {
  FinanceAgingBucket,
  FinanceBreakdownRow,
  FinanceCategoryBreakdown,
  FinanceSummary,
  FinanceTrendPoint,
} from '@/shared/types/prd.type';

interface Filters {
  from: string;
  to: string;
  dapur_id: number | null;
  supplier_id: number | null;
}

export interface FinanceAnalyticsProps {
  filters: Filters;
  summary: FinanceSummary;
  trend: FinanceTrendPoint[];
  arAging: FinanceAgingBucket[];
  apAging: FinanceAgingBucket[];
  byDapur: FinanceBreakdownRow[];
  bySupplier: FinanceBreakdownRow[];
  byCategory: FinanceCategoryBreakdown[];
  dapurs: { id: number; name: string }[];
  suppliers: { id: number; name: string }[];
}

const PRESETS = [
  { label: '7 hari', days: 7 },
  { label: '30 hari', days: 30 },
  { label: '90 hari', days: 90 },
];

function toDateString(d: Date): string {
  return d.toISOString().slice(0, 10);
}

/**
 * Moved here from the standalone /finance/dashboard page (removed) — lives
 * directly on the main dashboard now, below the per-role summary cards, for
 * whoever has a finance permission. Filter changes navigate `dashboard`
 * (not a dedicated finance route) since this is just a section of it; the
 * other roles' widgets ignore these query params and keep their own fixed
 * trailing-14-day window.
 */
export function FinanceAnalyticsSection({ filters, summary, trend, arAging, apAging, byDapur, bySupplier, byCategory, dapurs, suppliers }: FinanceAnalyticsProps) {
  const applyFilters = (patch: Partial<Filters>) => {
    router.get(
      route('dashboard'),
      { ...filters, ...patch },
      { preserveState: true, preserveScroll: true, replace: true },
    );
  };

  const applyPreset = (days: number) => {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    applyFilters({ from: toDateString(from), to: toDateString(to) });
  };

  const activePresetDays = (() => {
    const from = new Date(filters.from);
    const to = new Date(filters.to);
    const days = Math.round((to.getTime() - from.getTime()) / 86_400_000) + 1;

    return PRESETS.find((p) => p.days === days)?.days ?? null;
  })();

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between gap-3">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Analisa Keuangan</h2>
      </div>

      {/* Filters: one row, above everything it scopes. Date range first. */}
      <div className="flex flex-wrap items-center gap-3">
        <div className="flex items-center gap-1 rounded-md border border-gray-200 p-1 dark:border-gray-800">
          {PRESETS.map((preset) => (
            <Button
              key={preset.days}
              type="button"
              size="sm"
              variant={activePresetDays === preset.days ? 'default' : 'ghost'}
              onClick={() => applyPreset(preset.days)}
            >
              {preset.label}
            </Button>
          ))}
        </div>

        <input
          type="date"
          value={filters.from}
          onChange={(e) => applyFilters({ from: e.target.value })}
          className="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm dark:border-gray-700 dark:bg-gray-900"
        />
        <span className="text-sm text-gray-400">s/d</span>
        <input
          type="date"
          value={filters.to}
          onChange={(e) => applyFilters({ to: e.target.value })}
          className="h-9 rounded-md border border-gray-300 bg-white px-2 text-sm dark:border-gray-700 dark:bg-gray-900"
        />

        <Select
          value={filters.dapur_id ? String(filters.dapur_id) : 'all'}
          onValueChange={(v) => applyFilters({ dapur_id: v === 'all' ? null : Number(v) })}
        >
          <SelectTrigger className="w-44"><SelectValue placeholder="Semua Dapur" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Semua Dapur</SelectItem>
            {dapurs.map((d) => (
              <SelectItem key={d.id} value={String(d.id)}>{d.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>

        <Select
          value={filters.supplier_id ? String(filters.supplier_id) : 'all'}
          onValueChange={(v) => applyFilters({ supplier_id: v === 'all' ? null : Number(v) })}
        >
          <SelectTrigger className="w-44"><SelectValue placeholder="Semua Supplier" /></SelectTrigger>
          <SelectContent>
            <SelectItem value="all">Semua Supplier</SelectItem>
            {suppliers.map((s) => (
              <SelectItem key={s.id} value={String(s.id)}>{s.name}</SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {/* KPI row */}
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
        <StatTile label="Revenue" value={formatRupiah(summary.revenue)} hint="Invoice ISSUED + PAID" />
        <StatTile label="COGS" value={formatRupiah(summary.cogs)} hint="Bill ISSUED + PAID" />
        <StatTile
          label="Gross margin"
          value={formatRupiah(summary.gross_margin)}
          tone={summary.gross_margin >= 0 ? 'good' : 'bad'}
          hint={`${summary.margin_percent.toFixed(1)}% dari revenue`}
        />
        <StatTile label="AR outstanding" value={formatRupiah(summary.ar_outstanding)} hint="Belum dibayar Dapur" />
        <StatTile label="AP outstanding" value={formatRupiah(summary.ap_outstanding)} hint="Belum dibayar ke Supplier" />
        <StatTile label="Cash in" value={formatRupiah(summary.cash_in)} hint="Dibayar periode ini" />
        <StatTile label="Cash out" value={formatRupiah(summary.cash_out)} hint="Dibayar periode ini" />
        <StatTile
          label="Net cash flow"
          value={formatRupiah(summary.net_cash_flow)}
          tone={summary.net_cash_flow >= 0 ? 'good' : 'bad'}
        />
      </div>

      <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Revenue vs COGS</h3>
        <TrendLineChart data={trend} />
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Aging Piutang (AR)</h3>
          <BarList rows={arAging.map((b) => ({ label: b.bucket, value: b.total }))} sequential />
        </div>
        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Aging Utang (AP)</h3>
          <BarList rows={apAging.map((b) => ({ label: b.bucket, value: b.total }))} sequential />
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-2">
        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Margin per Dapur</h3>
          <GroupedBarList rows={byDapur.slice(0, 8).map((r) => ({ label: r.label, revenue: r.revenue, cogs: r.cogs }))} />
        </div>
        <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
          <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Margin per Supplier</h3>
          <GroupedBarList rows={bySupplier.slice(0, 8).map((r) => ({ label: r.label, revenue: r.revenue, cogs: r.cogs }))} />
        </div>
      </div>

      <div className="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
        <h3 className="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-200">Margin per Kategori Barang</h3>
        <GroupedBarList
          rows={byCategory.map((r) => ({ label: r.category === 'DRY_GOODS' ? 'Dry Goods' : 'Wet Goods', revenue: r.revenue, cogs: r.cogs }))}
        />
      </div>

      {/* Full detail tables — every number above is reachable here too, not gated behind hover/chart. */}
      <div className="grid gap-4 lg:grid-cols-2">
        <div>
          <h3 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Detail per Dapur</h3>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Dapur</TableHead>
                <TableHead className="text-right">Revenue</TableHead>
                <TableHead className="text-right">COGS</TableHead>
                <TableHead className="text-right">Margin</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {byDapur.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-medium">{row.label}</TableCell>
                  <TableCell className="text-right">{formatRupiah(row.revenue)}</TableCell>
                  <TableCell className="text-right">{formatRupiah(row.cogs)}</TableCell>
                  <TableCell className={cn('text-right', row.margin < 0 && 'text-red-600 dark:text-red-400')}>
                    {formatRupiah(row.margin)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>

        <div>
          <h3 className="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-200">Detail per Supplier</h3>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Supplier</TableHead>
                <TableHead className="text-right">Revenue</TableHead>
                <TableHead className="text-right">COGS</TableHead>
                <TableHead className="text-right">Margin</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {bySupplier.map((row) => (
                <TableRow key={row.id}>
                  <TableCell className="font-medium">{row.label}</TableCell>
                  <TableCell className="text-right">{formatRupiah(row.revenue)}</TableCell>
                  <TableCell className="text-right">{formatRupiah(row.cogs)}</TableCell>
                  <TableCell className={cn('text-right', row.margin < 0 && 'text-red-600 dark:text-red-400')}>
                    {formatRupiah(row.margin)}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </div>
      </div>
    </div>
  );
}
