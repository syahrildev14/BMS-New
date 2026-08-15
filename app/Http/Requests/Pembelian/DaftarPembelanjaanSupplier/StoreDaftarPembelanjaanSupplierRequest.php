<?php

namespace App\Http\Requests\Pembelian\DaftarPembelanjaanSupplier;

use Illuminate\Foundation\Http\FormRequest;

class StoreDaftarPembelanjaanSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'daftar_pembelanjaan_id' => [
                'required',
                'exists:daftar_pembelanjaans,id',
            ],

            'tanggal_pembelian' => [
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
            'daftar_pembelanjaan_id.required' => 'Daftar pembelanjaan wajib dipilih.',
            'daftar_pembelanjaan_id.exists' => 'Daftar pembelanjaan tidak ditemukan.',
            'tanggal_pembelian.required' => 'Tanggal pembelian wajib diisi.',
        ];
    }
}