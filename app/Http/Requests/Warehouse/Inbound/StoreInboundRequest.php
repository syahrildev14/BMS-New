<?php

namespace App\Http\Requests\Warehouse\Inbound;

use Illuminate\Foundation\Http\FormRequest;

class StoreInboundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daftar_pembelanjaan_supplier_id' => [
                'required',
                'exists:daftar_pembelanjaan_suppliers,id',
            ],

            'gudang_id' => [
                'required',
                'exists:gudangs,id',
            ],

            'tanggal_masuk' => [
                'required',
                'date',
            ],

            'keterangan' => [
                'nullable',
                'string',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'daftar_pembelanjaan_supplier_id.required' => 'Daftar pembelanjaan supplier wajib dipilih.',
            'daftar_pembelanjaan_supplier_id.exists' => 'Data tidak ditemukan.',
            'gudang_id.required' => 'Gudang wajib dipilih.',
            'gudang_id.exists' => 'Gudang tidak ditemukan.',
            'tanggal_masuk.required' => 'Tanggal masuk wajib diisi.',
        ];
    }
}