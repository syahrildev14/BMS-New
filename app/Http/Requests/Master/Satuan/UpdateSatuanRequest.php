<?php

namespace App\Http\Requests\Master\Satuan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSatuanRequest extends FormRequest
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
                Rule::unique('satuans', 'kode_satuan')->ignore($this->satuan),
            ],

            'nama_satuan' => [
                'required',
                'string',
                'max:100',
                Rule::unique('satuans', 'nama_satuan')->ignore($this->satuan),
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
