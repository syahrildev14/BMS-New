<?php

namespace App\Http\Requests\Master\Barang;

use Illuminate\Foundation\Http\FormRequest;

class StoreBarangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku_barang' => [
                'required',
                'string',
                'max:30',
                'unique:barangs,sku_barang'
            ],

            'nama_barang' => [
                'required',
                'string',
                'max:150'
            ],

            'satuan_id' => [
                'required',
                'exists:satuans,id'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'sku_barang.required' => 'SKU barang wajib diisi.',
            'sku_barang.unique' => 'SKU barang sudah digunakan.',

            'nama_barang.required' => 'Nama barang wajib diisi.',

            'satuan_id.required' => 'Satuan wajib dipilih.',
            'satuan_id.exists' => 'Satuan tidak ditemukan.',
        ];
    }
}
