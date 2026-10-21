<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Satuan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SatuanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/satuans/index', [
            'satuans' => Satuan::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Satuan::create($validated);

        return back()->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function update(Request $request, Satuan $satuan): RedirectResponse
    {
        $validated = $this->validated($request, $satuan);

        $satuan->update($validated);

        return back()->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Satuan $satuan): RedirectResponse
    {
        try {
            $satuan->delete();
        } catch (QueryException) {
            return back()->with('error', 'Satuan tidak bisa dihapus karena sudah dipakai di Barang.');
        }

        return back()->with('success', 'Satuan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Satuan $satuan = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:satuans,code,' . ($satuan?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
