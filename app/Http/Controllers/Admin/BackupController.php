<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/backups/index', [
            'backups' => Backup::with('creator')->latest()->get(),
        ]);
    }

    public function store(Request $request, BackupService $service): RedirectResponse
    {
        $service->create($request->user());

        return back()->with('success', 'Backup berhasil dibuat.');
    }

    public function download(Backup $backup): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($backup->disk_path), 404);

        return Storage::disk('local')->download($backup->disk_path, $backup->filename);
    }

    public function destroy(Backup $backup, BackupService $service): RedirectResponse
    {
        $service->delete($backup);

        return back()->with('success', 'Backup berhasil dihapus.');
    }
}
