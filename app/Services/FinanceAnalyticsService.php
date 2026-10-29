<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\SupplierBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only reporting layer on top of Invoice (AR) / SupplierBill (AP) /
 * OrderItem (category breakdown). Nothing here writes data — it only
 * aggregates what SplitInvoiceService / SupplierBillService already
 * reconciled, so there is one source of truth for "what's billable" and a
 * separate, disposable one for "how do we want to slice it for reporting".
 *
 * Revenue/COGS/margin are recognized at ISSUED+PAID (i.e. once billed,
 * matching accrual accounting) and filtered by the Order's delivery_date —
 * "what came in during this period". Cash flow is recognized only at PAID
 * and filtered by paid_at — "money that actually moved during this period".
 * These are deliberately different date dimensions of the same underlying
 * rows; don't conflate them.
 *
 * @phpstan-type Filters array{from?: ?string, to?: ?string, dapur_id?: ?int, supplier_id?: ?int}
 */
class FinanceAnalyticsService
{
    private const RECOGNIZED = ['ISSUED', 'PAID'];

    private const OUTSTANDING = ['ISSUED', 'OVERDUE'];

    /**
     * @param  Filters  $filters
     * @return array{revenue: float, cogs: float, gross_margin: float, margin_percent: float, ar_outstanding: float, ap_outstanding: float, cash_in: float, cash_out: float, net_cash_flow: float}
     */
    public function summary(array $filters): array
    {
        $revenue = (float) $this->invoiceQuery($filters)->whereIn('invoices.status', self::RECOGNIZED)->sum('invoices.total_amount');
        $cogs = (float) $this->billQuery($filters)->whereIn('supplier_bills.status', self::RECOGNIZED)->sum('supplier_bills.total_amount');
        $arOutstanding = (float) $this->invoiceQuery($filters)->whereIn('invoices.status', self::OUTSTANDING)->sum('invoices.total_amount');
        $apOutstanding = (float) $this->billQuery($filters)->whereIn('supplier_bills.status', self::OUTSTANDING)->sum('supplier_bills.total_amount');

        $cashIn = (float) $this->invoiceQuery($filters, forCashFlow: true)->where('invoices.status', 'PAID')->sum('invoices.total_amount');
        $cashOut = (float) $this->billQuery($filters, forCashFlow: true)->where('supplier_bills.status', 'PAID')->sum('supplier_bills.total_amount');

        $grossMargin = $revenue - $cogs;

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_margin' => $grossMargin,
            'margin_percent' => $revenue > 0 ? round($grossMargin / $revenue * 100, 2) : 0.0,
            'ar_outstanding' => $arOutstanding,
            'ap_outstanding' => $apOutstanding,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'net_cash_flow' => $cashIn - $cashOut,
        ];
    }

    /**
     * Revenue vs COGS per delivery_date — the trend line for the dashboard chart.
     *
     * @param  Filters  $filters
     * @return Collection<int, array{date: string, revenue: float, cogs: float, margin: float}>
     */
    public function trend(array $filters): Collection
    {
        $revenueByDate = $this->invoiceQuery($filters)
            ->whereIn('invoices.status', self::RECOGNIZED)
            ->selectRaw('orders.delivery_date as date, SUM(invoices.total_amount) as total')
            ->groupBy('orders.delivery_date')
            ->get()
            ->keyBy(fn ($row) => (string) $row->date);

        $cogsByDate = $this->billQuery($filters)
            ->whereIn('supplier_bills.status', self::RECOGNIZED)
            ->selectRaw('orders.delivery_date as date, SUM(supplier_bills.total_amount) as total')
            ->groupBy('orders.delivery_date')
            ->get()
            ->keyBy(fn ($row) => (string) $row->date);

        return $revenueByDate->keys()
            ->merge($cogsByDate->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $date) use ($revenueByDate, $cogsByDate) {
                $revenue = (float) ($revenueByDate[$date]->total ?? 0);
                $cogs = (float) ($cogsByDate[$date]->total ?? 0);

                return ['date' => $date, 'revenue' => $revenue, 'cogs' => $cogs, 'margin' => $revenue - $cogs];
            });
    }

    /**
     * @param  Filters  $filters
     * @return Collection<int, array{dapur: string, revenue: float, cogs: float, margin: float}>
     */
    public function breakdownByDapur(array $filters): Collection
    {
        $revenue = $this->invoiceQuery($filters)
            ->whereIn('invoices.status', self::RECOGNIZED)
            ->selectRaw('orders.dapur_id as id, dapurs.name as label, SUM(invoices.total_amount) as total')
            ->join('dapurs', 'dapurs.id', '=', 'orders.dapur_id')
            ->groupBy('orders.dapur_id', 'dapurs.name')
            ->get()
            ->keyBy('id');

        $cogs = $this->billQuery($filters)
            ->whereIn('supplier_bills.status', self::RECOGNIZED)
            ->selectRaw('supplier_bills.dapur_id as id, dapurs.name as label, SUM(supplier_bills.total_amount) as total')
            ->join('dapurs', 'dapurs.id', '=', 'supplier_bills.dapur_id')
            ->groupBy('supplier_bills.dapur_id', 'dapurs.name')
            ->get()
            ->keyBy('id');

        return $this->mergeBreakdown($revenue, $cogs);
    }

    /**
     * @param  Filters  $filters
     * @return Collection<int, array{supplier: string, revenue: float, cogs: float, margin: float}>
     */
    public function breakdownBySupplier(array $filters): Collection
    {
        $revenue = $this->invoiceQuery($filters)
            ->whereIn('invoices.status', self::RECOGNIZED)
            ->selectRaw('invoices.supplier_id as id, suppliers.name as label, SUM(invoices.total_amount) as total')
            ->join('suppliers', 'suppliers.id', '=', 'invoices.supplier_id')
            ->groupBy('invoices.supplier_id', 'suppliers.name')
            ->get()
            ->keyBy('id');

        $cogs = $this->billQuery($filters)
            ->whereIn('supplier_bills.status', self::RECOGNIZED)
            ->selectRaw('supplier_bills.supplier_id as id, suppliers.name as label, SUM(supplier_bills.total_amount) as total')
            ->join('suppliers', 'suppliers.id', '=', 'supplier_bills.supplier_id')
            ->groupBy('supplier_bills.supplier_id', 'suppliers.name')
            ->get()
            ->keyBy('id');

        return $this->mergeBreakdown($revenue, $cogs);
    }

    /**
     * DRY_GOODS vs WET_GOODS margin — read straight from order_items rather
     * than Invoice/SupplierBill, since `category` lives on Item and an
     * invoice/bill can blend both categories under one supplier.
     *
     * @param  Filters  $filters
     * @return Collection<int, array{category: string, revenue: float, cogs: float, margin: float}>
     */
    public function breakdownByCategory(array $filters): Collection
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('items', 'items.id', '=', 'order_items.item_id')
            ->whereNotNull('order_items.qty_received')
            ->whereNotNull('order_items.final_selling_price')
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('orders.delivery_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('orders.delivery_date', '<=', $v))
            ->when($filters['dapur_id'] ?? null, fn ($q, $v) => $q->where('orders.dapur_id', $v))
            ->when($filters['supplier_id'] ?? null, fn ($q, $v) => $q->where('order_items.supplier_id', $v))
            ->selectRaw('
                items.category as category,
                SUM((order_items.qty_received - order_items.qty_rejected) * order_items.final_selling_price) as revenue,
                SUM((order_items.qty_received - order_items.qty_rejected) * order_items.final_cost_price) as cogs
            ')
            ->groupBy('items.category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'revenue' => (float) $row->revenue,
                'cogs' => (float) $row->cogs,
                'margin' => (float) $row->revenue - (float) $row->cogs,
            ]);
    }

    /**
     * @param  Filters  $filters
     * @return Collection<int, array{bucket: string, total: float, count: int}>
     */
    public function arAging(array $filters): Collection
    {
        return $this->aging($this->invoiceQuery($filters)->whereIn('invoices.status', self::OUTSTANDING), 'invoices');
    }

    /**
     * @param  Filters  $filters
     * @return Collection<int, array{bucket: string, total: float, count: int}>
     */
    public function apAging(array $filters): Collection
    {
        return $this->aging($this->billQuery($filters)->whereIn('supplier_bills.status', self::OUTSTANDING), 'supplier_bills');
    }

    /**
     * @return Collection<int, array{bucket: string, total: float, count: int}>
     */
    private function aging(\Illuminate\Database\Eloquent\Builder $query, string $table): Collection
    {
        $today = Carbon::today();

        $rows = $query->get(["{$table}.total_amount", "{$table}.due_date"]);

        $buckets = [
            'Belum jatuh tempo' => ['total' => 0.0, 'count' => 0],
            '1-7 hari' => ['total' => 0.0, 'count' => 0],
            '8-14 hari' => ['total' => 0.0, 'count' => 0],
            '15-30 hari' => ['total' => 0.0, 'count' => 0],
            '>30 hari' => ['total' => 0.0, 'count' => 0],
        ];

        foreach ($rows as $row) {
            $daysOverdue = $row->due_date ? $today->diffInDays(Carbon::parse($row->due_date), false) * -1 : -1;

            $bucket = match (true) {
                $daysOverdue <= 0 => 'Belum jatuh tempo',
                $daysOverdue <= 7 => '1-7 hari',
                $daysOverdue <= 14 => '8-14 hari',
                $daysOverdue <= 30 => '15-30 hari',
                default => '>30 hari',
            };

            $buckets[$bucket]['total'] += (float) $row->total_amount;
            $buckets[$bucket]['count']++;
        }

        return collect($buckets)->map(fn ($data, $bucket) => [
            'bucket' => $bucket,
            'total' => $data['total'],
            'count' => $data['count'],
        ])->values();
    }

    /**
     * @param  Filters  $filters
     */
    private function invoiceQuery(array $filters, bool $forCashFlow = false)
    {
        $query = Invoice::query()->join('orders', 'orders.id', '=', 'invoices.order_id');

        return $this->applyFilters($query, $filters, 'invoices', $forCashFlow);
    }

    /**
     * @param  Filters  $filters
     */
    private function billQuery(array $filters, bool $forCashFlow = false)
    {
        $query = SupplierBill::query()->join('orders', 'orders.id', '=', 'supplier_bills.order_id');

        return $this->applyFilters($query, $filters, 'supplier_bills', $forCashFlow);
    }

    /**
     * @param  Filters  $filters
     */
    private function applyFilters(\Illuminate\Database\Eloquent\Builder $query, array $filters, string $table, bool $forCashFlow)
    {
        return $query
            ->when(
                $filters['from'] ?? null,
                fn ($q, $v) => $forCashFlow ? $q->whereDate("{$table}.paid_at", '>=', $v) : $q->whereDate('orders.delivery_date', '>=', $v)
            )
            ->when(
                $filters['to'] ?? null,
                fn ($q, $v) => $forCashFlow ? $q->whereDate("{$table}.paid_at", '<=', $v) : $q->whereDate('orders.delivery_date', '<=', $v)
            )
            ->when($filters['dapur_id'] ?? null, fn ($q, $v) => $q->where('orders.dapur_id', $v))
            ->when($filters['supplier_id'] ?? null, fn ($q, $v) => $q->where("{$table}.supplier_id", $v));
    }

    /**
     * @return Collection<int, array{id: int, label: string, revenue: float, cogs: float, margin: float}>
     */
    private function mergeBreakdown(Collection $revenue, Collection $cogs): Collection
    {
        return $revenue->keys()
            ->merge($cogs->keys())
            ->unique()
            ->map(function ($id) use ($revenue, $cogs) {
                $revenueRow = $revenue[$id] ?? null;
                $cogsRow = $cogs[$id] ?? null;
                $revenueTotal = (float) ($revenueRow->total ?? 0);
                $cogsTotal = (float) ($cogsRow->total ?? 0);

                return [
                    'id' => (int) $id,
                    'label' => $revenueRow->label ?? $cogsRow->label ?? ('#' . $id),
                    'revenue' => $revenueTotal,
                    'cogs' => $cogsTotal,
                    'margin' => $revenueTotal - $cogsTotal,
                ];
            })
            ->sortByDesc('revenue')
            ->values();
    }
}
