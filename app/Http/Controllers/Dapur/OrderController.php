<?php

namespace App\Http\Controllers\Dapur;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Item;
use App\Models\Order;
use App\Models\Supplier;
use App\Services\RevisionGateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $dapurId = $request->user()->dapur_id;

        return Inertia::render('dapur/orders/index', [
            'orders' => Order::where('dapur_id', $dapurId)
                ->latest('delivery_date')
                ->with('orderItems.item')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('dapur/orders/create', [
            'items' => Item::with(['defaultSupplier', 'satuan'])->where('is_active', true)->get(),
            'suppliers' => Supplier::all(),
        ]);
    }

    public function store(StoreOrderRequest $request, RevisionGateService $revisionGate): RedirectResponse
    {
        $dapurId = $request->user()->dapur_id;
        $validated = $request->validated();

        $order = Order::firstOrCreate(
            ['dapur_id' => $dapurId, 'delivery_date' => $validated['delivery_date']],
            [
                'order_number' => sprintf('ORD/%s/%s', now()->format('Ymd'), $dapurId),
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $result = $revisionGate->submitRevision($order, $validated['items'], $request->user());

        return redirect()
            ->route('dapur.orders.index')
            ->with('success', "Order berhasil dikirim (tier: {$result['tier']}).");
    }

    public function show(Request $request, Order $order): Response
    {
        abort_unless($order->dapur_id === $request->user()->dapur_id, 403);

        return Inertia::render('dapur/orders/show', [
            'order' => $order->load('orderItems.item', 'suratJalans'),
        ]);
    }
}
