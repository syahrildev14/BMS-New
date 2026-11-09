<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentTemplate;
use App\Services\DocumentTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DocumentTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/document-templates/index', [
            'templates' => DocumentTemplate::with('uploadedBy:id,name')->latest()->get(),
        ]);
    }

    public function store(Request $request, DocumentTemplateService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', Rule::in(DocumentTemplate::TYPES)],
            'file' => ['required', 'file', 'extensions:docx', 'max:5120'],
        ]);

        $service->store($validated['name'], $validated['document_type'], $request->file('file'), $request->user());

        return back()->with('success', 'Template berhasil diunggah.');
    }

    /** The file itself is never replaced; only the name and status can change. */
    public function update(Request $request, DocumentTemplate $documentTemplate): RedirectResponse
    {
        $documentTemplate->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]));

        return back()->with('success', 'Template berhasil diperbarui.');
    }

    public function destroy(DocumentTemplate $documentTemplate, DocumentTemplateService $service): RedirectResponse
    {
        if (! $service->delete($documentTemplate)) {
            return back()->with('error', 'Template sudah dipakai dokumen, jadi hanya bisa dinonaktifkan.');
        }

        return back()->with('success', 'Template berhasil dihapus.');
    }
}
