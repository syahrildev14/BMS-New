<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Satuan;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/items/index', [
            'items' => Item::with(['defaultSupplier', 'satuan'])->orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'satuans' => Satuan::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Item::create($validated);

        return back()->with('success', 'Barang berhasil ditambahkan.');
    }

    public function update(Request $request, Item $item): RedirectResponse
    {
        $validated = $this->validated($request, $item);

        $item->update($validated);

        return back()->with('success', 'Barang berhasil diperbarui.');
    }

    public function destroy(Item $item): RedirectResponse
    {
        try {
            $item->delete();
        } catch (QueryException) {
            return back()->with('error', 'Barang tidak bisa dihapus karena sudah dipakai di transaksi.');
        }

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Item $item = null): array
    {
        return $request->validate([
            'sku' => ['required', 'string', 'max:255', 'unique:items,sku,' . ($item?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:DRY_GOODS,WET_GOODS'],
            'satuan_id' => ['required', 'integer', 'exists:satuans,id'],
            // DRY_GOODS is stocked via Gudang and auto-drafts a PO against this
            // supplier when stock runs short (DeliveryNoteService) — without it
            // a shortfall line can never get a draft PO and is stuck forever.
            'default_supplier_id' => [
                Rule::requiredIf($request->input('category') === 'DRY_GOODS'),
                'nullable',
                'integer',
                'exists:suppliers,id',
            ],
            'margin_type' => ['required', 'in:PERCENTAGE,FIXED_IDR'],
            'margin_value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);
    }
}
