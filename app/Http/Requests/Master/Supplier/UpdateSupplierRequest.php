<?php

namespace App\Http\Requests\Master\Supplier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_supplier' => [
                'required',
                'string',
                'max:150'
            ],

            'alamat_supplier' => [
                'required',
                'string'
            ],

            'nomor_telepon_supplier' => [
                'required',
                'string',
                'max:20'
            ],

            'kategori' => [
                'required',
                Rule::in(['basah', 'kering'])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_supplier.required' => 'Nama supplier wajib diisi.',
            'alamat_supplier.required' => 'Alamat supplier wajib diisi.',
            'nomor_telepon_supplier.required' => 'Nomor telepon wajib diisi.',
            'kategori.required' => 'Kategori wajib dipilih.',
            'kategori.in' => 'Kategori tidak valid.',
        ];
    }
}