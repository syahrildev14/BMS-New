<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\TemplateSurat\StoreTemplateSuratRequest;
use App\Http\Requests\Master\TemplateSurat\UpdateTemplateSuratRequest;
use App\Http\Resources\Master\TemplateSuratResource;
use App\Models\TemplateSurat;

class TemplateSuratController extends Controller
{
    public function index()
    {
        $perPage = request('per_page', 10);

        $template = TemplateSurat::when(request('search'), function ($query) {
                $query->where('nama_template', 'like', '%' . request('search') . '%')
                    ->orWhere('jenis_template', 'like', '%' . request('search') . '%');
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'success' => true,
            'message' => 'Data template surat berhasil diambil.',
            'data' => TemplateSuratResource::collection($template),
        ]);
    }

    public function store(StoreTemplateSuratRequest $request)
    {
        $template = TemplateSurat::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template surat berhasil ditambahkan.',
            'data' => new TemplateSuratResource($template),
        ], 201);
    }

    public function show(TemplateSurat $templateSurat)
    {
        return response()->json([
            'success' => true,
            'message' => 'Detail template surat.',
            'data' => new TemplateSuratResource($templateSurat),
        ]);
    }

    public function update(UpdateTemplateSuratRequest $request, TemplateSurat $templateSurat)
    {
        $templateSurat->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Template surat berhasil diperbarui.',
            'data' => new TemplateSuratResource($templateSurat->fresh()),
        ]);
    }

    public function destroy(TemplateSurat $templateSurat)
    {
        $templateSurat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template surat berhasil dihapus.',
        ]);
    }
}