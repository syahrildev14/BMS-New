<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppearanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppearanceController extends Controller
{
    public function index(AppearanceService $service): Response
    {
        return Inertia::render('admin/appearance/index', [
            'color' => $service->primaryColor(),
            'defaultColor' => AppearanceService::DEFAULT_COLOR,
            'presets' => AppearanceService::PRESETS,
        ]);
    }

    public function update(Request $request, AppearanceService $service): RedirectResponse
    {
        $validated = $request->validate([
            'color' => ['required', 'string'],
        ]);

        $service->update($validated['color'], $request->user());

        return back()->with('success', 'Warna tema berhasil disimpan.');
    }

    public function destroy(Request $request, AppearanceService $service): RedirectResponse
    {
        $service->reset($request->user());

        return back()->with('success', 'Warna tema dikembalikan ke bawaan.');
    }
}
