<?php

namespace App\Http\Controllers;

use App\Models\Dapur;
use App\Models\Gudang;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\SupplierBill;
use App\Services\FinanceAnalyticsService;
use App\Services\RevisionGateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Landing page after login — no permission middleware (every authenticated
 * user reaches it), so what's shown is built from the user's OWN permissions/
 * scoping rather than one fixed layout. Mirrors the sidebar's
 * `can(permission)` filtering in AppLayout.tsx: a widget key is only present
 * in the response when the user actually has the matching permission, so the
 * frontend never has to re-check auth to decide what to render.
 */
class DashboardController extends Controller
{
    private const TREND_DAYS = 14;

    public function index(Request $request, RevisionGateService $revisionGate, FinanceAnalyticsService $analytics): Response
    {
        $user = $request->user();
        $permissions = $user->getAllPermissions()->pluck('name');
        $can = fn (string $permission) => $permissions->contains($permission);

        $widgets = [];

        if ($can('orders.manage') && $user->dapur_id) {
            $widgets['dapur'] = [
                'open_orders' => Order::where('dapur_id', $user->dapur_id)
                    ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
                    ->count(),
                'pending_stt' => OrderItem::whereHas('order', fn ($q) => $q->where('dapur_id', $user->dapur_id))
                    ->whereNotNull('surat_jalan_id')
                    ->whereNull('qty_received')
                    ->count(),
                'trend' => $this->dailyCounts(
                    Order::where('dapur_id', $user->dapur_id),
                    'delivery_date',
                ),
            ];
        }

        if ($can('delivery-notes.manage')) {
            $widgets['ops'] = [
                'orders_pending_sj' => Order::whereIn('status', ['SUBMITTED', 'LOCKED', 'PROCESSING'])
                    ->whereHas('orderItems', fn ($q) => $q->whereNull('surat_jalan_id'))
                    ->count(),
            ];
        }

        if ($can('revisions.approve')) {
            $widgets['ops']['pending_revisions'] = $revisionGate->pendingApprovals()->count();
        }

        if ($can('purchase-orders.manage')) {
            $widgets['purchasing'] = [
                'draft_pos' => PurchaseOrder::where('status', 'DRAFT')->count(),
            ];
        }

        if ($can('stock.manage')) {
            $gudang = Gudang::query()->first();

            $widgets['gudang'] = [
                'pos_awaiting_receive' => PurchaseOrder::whereIn('status', ['SENT', 'PARTIALLY_RECEIVED'])->count(),
                'out_of_stock_items' => $gudang ? $this->countOutOfStockItems($gudang->id) : 0,
            ];
        }

        if ($can('invoices.manage') || $can('invoices.approve') || $can('supplier-bills.manage') || $can('supplier-bills.approve')) {
            // Moved here from the removed /finance/dashboard page — full P&L/
            // cash-flow analytics now lives directly on the main dashboard.
            // Filters come from the query string (date-range presets, Dapur,
            // Supplier), same as the old standalone page, just posting back
            // to `dashboard` instead of a dedicated finance route.
            $filters = [
                'from' => $request->query('from') ?: now()->subDays(29)->toDateString(),
                'to' => $request->query('to') ?: now()->toDateString(),
                'dapur_id' => $request->query('dapur_id') ? (int) $request->query('dapur_id') : null,
                'supplier_id' => $request->query('supplier_id') ? (int) $request->query('supplier_id') : null,
            ];

            $summary = $analytics->summary($filters);

            $widgets['finance'] = [
                'filters' => $filters,
                'summary' => $summary,
                'trend' => $analytics->trend($filters)->values(),
                'arAging' => $analytics->arAging($filters)->values(),
                'apAging' => $analytics->apAging($filters)->values(),
                'byDapur' => $analytics->breakdownByDapur($filters)->values(),
                'bySupplier' => $analytics->breakdownBySupplier($filters)->values(),
                'byCategory' => $analytics->breakdownByCategory($filters)->values(),
                'dapurs' => Dapur::orderBy('name')->get(['id', 'name']),
                'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            ];

            if ($can('invoices.approve')) {
                $widgets['finance']['draft_invoices'] = Invoice::where('status', 'DRAFT')->count();
            }

            if ($can('supplier-bills.approve')) {
                $widgets['finance']['draft_bills'] = SupplierBill::where('status', 'DRAFT')->count();
            }
        }

        return Inertia::render('dashboard', ['widgets' => $widgets]);
    }

    /**
     * Daily row count for the trailing N days (today inclusive), zero-filled
     * for days with no rows — a sparse groupBy() would otherwise skip them
     * and make the chart's x-axis gaps look like missing data.
     *
     * @return array<int, array{label: string, value: int}>
     */
    private function dailyCounts(Builder $query, string $dateColumn): array
    {
        $from = now()->subDays(self::TREND_DAYS - 1)->startOfDay();
        $to = now()->endOfDay();

        $counts = $query
            ->whereDate($dateColumn, '>=', $from->toDateString())
            ->whereDate($dateColumn, '<=', $to->toDateString())
            ->selectRaw("DATE({$dateColumn}) as date, COUNT(*) as total")
            ->groupBy('date')
            ->pluck('total', 'date');

        $days = collect();
        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            $days->push([
                'label' => $date->format('d/m'),
                'value' => (int) ($counts[$date->toDateString()] ?? 0),
            ]);
        }

        return $days->values()->toArray();
    }

    /**
     * DRY_GOODS items with zero stock on hand — same "qty_on_hand" derivation
     * as Gudang\StockController (SUM(qty_remaining) per item), just counting
     * the zero/negative side instead of listing every item's quantity.
     */
    private function countOutOfStockItems(int $gudangId): int
    {
        $stockedItemIds = StockLot::where('gudang_id', $gudangId)
            ->groupBy('item_id')
            ->havingRaw('SUM(qty_remaining) > 0')
            ->pluck('item_id');

        return Item::where('category', 'DRY_GOODS')
            ->where('is_active', true)
            ->whereNotIn('id', $stockedItemIds)
            ->count();
    }
}
