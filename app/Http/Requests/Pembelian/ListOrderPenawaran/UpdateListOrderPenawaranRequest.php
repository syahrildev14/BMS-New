<?php

namespace App\Http\Requests\Pembelian\ListOrderPenawaran;

use Illuminate\Foundation\Http\FormRequest;

class UpdateListOrderPenawaranRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'tanggal_pesan' => [
                'required',
                'date',
            ],

            'tanggal_kirim' => [
                'required',
                'date',
                'after_or_equal:tanggal_pesan',
            ],

            'sppg_id' => [
                'required',
                'exists:sppgs,id',
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

            'details.*.qty' => [
                'required',
                'numeric',
                'gt:0',
            ],

            'details.*.keterangan' => [
                'nullable',
                'string',
            ],

        ];
    }
}
