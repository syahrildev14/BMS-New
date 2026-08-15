<?php

namespace App\Http\Requests\Master\TemplateSurat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTemplateSuratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_template' => ['required','string','max:150'],

            'jenis_template' => [
                'required',
                Rule::in([
                    'invoice',
                    'surat_jalan',
                    'tanda_terima'
                ])
            ],

            'isi_template' => ['required','string'],

            'status' => [
                'required',
                Rule::in(['aktif','nonaktif'])
            ]
        ];
    }
}