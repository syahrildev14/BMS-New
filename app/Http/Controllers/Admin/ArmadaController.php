<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Armada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ArmadaController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/armadas/index', [
            'armadas' => Armada::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Armada::create($this->validated($request));

        return back()->with('success', 'Armada berhasil ditambahkan.');
    }

    public function update(Request $request, Armada $armada): RedirectResponse
    {
        $armada->update($this->validated($request));

        return back()->with('success', 'Armada berhasil diperbarui.');
    }

    public function destroy(Armada $armada): RedirectResponse
    {
        $armada->delete();

        return back()->with('success', 'Armada berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'plate_number' => ['required', 'string', 'max:20'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
