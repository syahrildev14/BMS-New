<?php

namespace App\Http\Requests\Penjualan;

use Illuminate\Foundation\Http\FormRequest;

class StorePenjualanRequest extends FormRequest
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

            'gudang_id' => [
                'required',
                'exists:gudangs,id',
            ],

            'tanggal' => [
                'required',
                'date',
            ],

            'keterangan' => [
                'nullable',
                'string',
            ],

        ];
    }
}