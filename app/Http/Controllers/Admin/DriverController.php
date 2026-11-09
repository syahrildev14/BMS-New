<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/drivers/index', [
            'drivers' => Driver::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Driver::create($this->validated($request));

        return back()->with('success', 'Driver berhasil ditambahkan.');
    }

    public function update(Request $request, Driver $driver): RedirectResponse
    {
        $driver->update($this->validated($request));

        return back()->with('success', 'Driver berhasil diperbarui.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        $driver->delete();

        return back()->with('success', 'Driver berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);
    }
}
