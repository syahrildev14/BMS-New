<?php

namespace App\Http\Requests\Pembelian\DaftarPembelanjaan;

use Illuminate\Foundation\Http\FormRequest;

class StoreDaftarPembelanjaanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'list_order_penawaran_id' => [
                'required',
                'exists:list_order_penawarans,id',
            ],

            'tanggal_pesan' => [
                'required',
                'date',
            ],

            'keterangan' => [
                'nullable',
                'string',
            ],

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.barang_id' => [
                'required',
                'exists:barangs,id',
            ],

            'details.*.supplier_id' => [
                'required',
                'exists:suppliers,id',
            ],

            'details.*.qty' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'details.*.harga' => [
                'required',
                'numeric',
                'gt:0',
            ],

        ];
    }

    public function messages(): array
    {
        return [
            'details.required' => 'Minimal harus ada satu barang.',
            'details.min' => 'Minimal harus ada satu barang.',
        ];
    }
}