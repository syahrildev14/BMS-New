<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupplierBill;
use App\Services\SupplierBillService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Accounts Payable counterpart of Finance\InvoiceController — same
 * maker-checker shape (generate DRAFT -> approve/reject -> pay), grouped by
 * the Order's delivery_date like invoices, just billing the Supplier instead
 * of the Dapur.
 */
class SupplierBillController extends Controller
{
    public function index(): Response
    {
        $groups = SupplierBill::query()
            ->join('orders', 'orders.id', '=', 'supplier_bills.order_id')
            ->selectRaw('orders.delivery_date as date, COUNT(supplier_bills.id) as bill_count, SUM(supplier_bills.total_amount) as total_amount')
            ->groupBy('orders.delivery_date')
            ->orderByDesc('orders.delivery_date')
            ->get();

        return Inertia::render('finance/supplier-bills/index', [
            'groups' => $groups,
            'billableOrders' => Order::with('dapur')
                ->whereHas('orderItems', fn ($query) => $query->whereNotNull('qty_received'))
                ->get(),
        ]);
    }

    public function byDate(string $date): Response
    {
        return Inertia::render('finance/supplier-bills/show', [
            'date' => $date,
            'bills' => SupplierBill::with(['order', 'dapur', 'supplier'])
                ->whereHas('order', fn ($query) => $query->whereDate('delivery_date', $date))
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, SupplierBillService $service): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $bills = $service->generateBillsForOrder($order);

        return back()->with('success', "{$bills->count()} draft bill ke supplier dibuat, menunggu persetujuan.");
    }

    public function approve(SupplierBill $supplierBill, SupplierBillService $service): RedirectResponse
    {
        $service->approveBill($supplierBill);

        return back()->with('success', "Bill {$supplierBill->bill_number} disetujui dan diterbitkan.");
    }

    public function reject(SupplierBill $supplierBill, SupplierBillService $service): RedirectResponse
    {
        $service->rejectBill($supplierBill);

        return back()->with('success', "Bill {$supplierBill->bill_number} ditolak.");
    }

    public function pay(Request $request, SupplierBill $supplierBill, SupplierBillService $service): RedirectResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'max:100'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $service->markAsPaid($supplierBill, $validated);

        return back()->with('success', "Bill {$supplierBill->bill_number} ditandai lunas.");
    }
}
