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
        return Inertia::render('finance/invoices/index', [
            'invoices' => Invoice::with(['order', 'dapur', 'supplier'])->latest()->get(),
            'billableOrders' => Order::with('dapur')
                ->whereHas('orderItems', fn ($query) => $query->whereNotNull('qty_received'))
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

        return back()->with('success', "{$invoices->count()} invoice berhasil diterbitkan.");
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
