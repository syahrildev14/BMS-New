<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateDeliveryNoteRequest;
use App\Models\Order;
use App\Services\DeliveryNoteService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryNoteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ops/delivery-notes/index', [
            'orders' => Order::whereIn('status', ['SUBMITTED', 'LOCKED', 'PROCESSING'])
                ->whereHas('orderItems', fn ($query) => $query->whereNull('surat_jalan_id'))
                ->with('dapur')
                ->withCount(['orderItems as pending_items_count' => fn ($query) => $query->whereNull('surat_jalan_id')])
                ->withCount('suratJalans')
                ->get(),
        ]);
    }

    public function store(GenerateDeliveryNoteRequest $request, DeliveryNoteService $service): RedirectResponse
    {
        $result = $service->generateSuratJalan(
            $request->validated()['order_id'],
            $request->user()->id
        );

        $messages = [];

        if ($result['surat_jalan']) {
            $messages[] = "Surat Jalan {$result['surat_jalan']->sj_number} berhasil diterbitkan.";
        }

        if ($result['purchase_orders']->isNotEmpty()) {
            $poNumbers = $result['purchase_orders']->pluck('po_number')->implode(', ');
            $messages[] = "Stok kurang untuk sebagian item — draft PO dibuat: {$poNumbers}.";
        }

        return back()->with('success', implode(' ', $messages));
    }
}
