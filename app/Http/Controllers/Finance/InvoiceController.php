<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\SplitInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function index(): Response
    {
        // Grouped by the Order's delivery_date (when the goods were actually
        // received by Dapur) rather than when the invoice document itself was
        // generated — Finance thinks in terms of "what came in on this date",
        // not "what I happened to click Generate for on this date".
        $groups = Invoice::query()
            ->join('orders', 'orders.id', '=', 'invoices.order_id')
            ->selectRaw('orders.delivery_date as date, COUNT(invoices.id) as invoice_count, SUM(invoices.total_amount) as total_amount')
            ->groupBy('orders.delivery_date')
            ->orderByDesc('orders.delivery_date')
            ->get();

        return Inertia::render('finance/invoices/index', [
            'groups' => $groups,
            'billableOrders' => Order::with('dapur')
                ->whereHas('orderItems', fn ($query) => $query->whereNotNull('qty_received'))
                ->get(),
        ]);
    }

    public function byDate(string $date): Response
    {
        return Inertia::render('finance/invoices/show', [
            'date' => $date,
            'invoices' => Invoice::with(['order', 'dapur', 'supplier'])
                ->whereHas('order', fn ($query) => $query->whereDate('delivery_date', $date))
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request, SplitInvoiceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ]);

        $order = Order::findOrFail($validated['order_id']);
        $invoices = $service->generateInvoicesForOrder($order);

        return back()->with('success', "{$invoices->count()} draft invoice dibuat, menunggu persetujuan.");
    }

    public function approve(Invoice $invoice, SplitInvoiceService $service): RedirectResponse
    {
        $service->approveInvoice($invoice);

        return back()->with('success', "Invoice {$invoice->invoice_number} disetujui dan diterbitkan.");
    }

    public function reject(Invoice $invoice, SplitInvoiceService $service): RedirectResponse
    {
        $service->rejectInvoice($invoice);

        return back()->with('success', "Invoice {$invoice->invoice_number} ditolak.");
    }

    public function download(Invoice $invoice): StreamedResponse
    {
        abort_unless($invoice->pdf_url && Storage::disk('local')->exists($invoice->pdf_url), 404);

        return Storage::disk('local')->download(
            $invoice->pdf_url,
            str_replace('/', '-', $invoice->invoice_number) . '.pdf'
        );
    }
}
