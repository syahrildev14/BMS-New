<?php

namespace App\Http\Requests\Master\Satuan;

use Illuminate\Foundation\Http\FormRequest;


class StoreSatuanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'kode_satuan' => [
                'required',
                'string',
                'max:20',
                'unique:satuans,kode_satuan'
            ],

            'nama_satuan' => [
                'required',
                'string',
                'max:100',
                'unique:satuans,nama_satuan'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_satuan.required' => 'Kode satuan wajib diisi.',
            'kode_satuan.unique' => 'Kode satuan sudah digunakan.',

            'nama_satuan.required' => 'Nama satuan wajib diisi.',
            'nama_satuan.unique' => 'Nama satuan sudah digunakan.',
        ];
    }
}
