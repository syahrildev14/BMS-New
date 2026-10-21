<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dapur;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DapurController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/dapurs/index', [
            'dapurs' => Dapur::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Dapur::create($validated);

        return back()->with('success', 'Dapur berhasil ditambahkan.');
    }

    public function update(Request $request, Dapur $dapur): RedirectResponse
    {
        $validated = $this->validated($request, $dapur);

        $dapur->update($validated);

        return back()->with('success', 'Dapur berhasil diperbarui.');
    }

    public function destroy(Dapur $dapur): RedirectResponse
    {
        try {
            $dapur->delete();
        } catch (QueryException) {
            return back()->with('error', 'Dapur tidak bisa dihapus karena sudah dipakai di transaksi.');
        }

        return back()->with('success', 'Dapur berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Dapur $dapur = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:255', 'unique:dapurs,code,' . ($dapur?->id ?? 'NULL')],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_phone' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
