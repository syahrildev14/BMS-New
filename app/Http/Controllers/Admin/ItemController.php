<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Satuan;
use App\Models\Supplier;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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

    /**
     * Aktifkan / nonaktifkan barang. Barang nonaktif tetap tersimpan beserta
     * riwayatnya, tetapi tidak muncul lagi di pilihan Order Dapur, harga harian,
     * dan nota pembelian.
     */
    public function toggleActive(Item $item): RedirectResponse
    {
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', $item->is_active
            ? "Barang \"{$item->name}\" diaktifkan kembali."
            : "Barang \"{$item->name}\" dinonaktifkan.");
    }

    public function destroy(Item $item): RedirectResponse
    {
        try {
            $item->delete();
        } catch (QueryException) {
            return back()->with('error', $this->alasanTidakBisaDihapus($item));
        }

        return back()->with('success', 'Barang berhasil dihapus.');
    }

    /**
     * Sebutkan data apa saja yang masih menahan penghapusan barang
     * (foreign key RESTRICT), supaya admin tahu apa yang harus dibereskan.
     */
    private function alasanTidakBisaDihapus(Item $item): string
    {
        $bagian = [];
        $hitung = fn (string $tabel) => DB::table($tabel)->where('item_id', $item->id)->count();

        foreach ([
            'order_items' => 'item pesanan dapur',
            'purchase_order_items' => 'item PO',
            'purchase_note_items' => 'item nota pembelian',
        ] as $tabel => $label) {
            if ($n = $hitung($tabel)) {
                $bagian[] = "{$n} {$label}";
            }
        }

        $gerak = DB::table('stock_movements')->where('item_id', $item->id)
            ->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type');
        if ($opname = (int) ($gerak['OPNAME_ADJUST'] ?? 0)) {
            $bagian[] = "{$opname} riwayat stok opname/penyesuaian";
        }
        if ($lain = (int) $gerak->except('OPNAME_ADJUST')->sum()) {
            $bagian[] = "{$lain} riwayat keluar/masuk stok";
        }

        $lot = DB::table('stock_lots')->where('item_id', $item->id)
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(qty_remaining), 0) as sisa')->first();
        if ($lot && $lot->n) {
            $sisa = rtrim(rtrim(number_format((float) $lot->sisa, 2, ',', '.'), '0'), ',');
            $bagian[] = "{$lot->n} lot stok gudang (sisa {$sisa})";
        }

        if (! $bagian) {
            return 'Barang tidak bisa dihapus karena masih dipakai oleh data lain.';
        }

        return 'Barang tidak bisa dihapus karena masih tercatat di: ' . implode(', ', $bagian) . '.'
            . ($item->is_active ? ' Gunakan tombol Nonaktifkan bila barang ini tidak dipakai lagi.' : '');
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
