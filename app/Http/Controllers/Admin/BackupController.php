<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function export(Request $request, BackupService $service): StreamedResponse
    {
        $backup = $service->create($request->user());

        return Storage::disk('local')->download($backup->disk_path, $backup->filename);
    }
}
