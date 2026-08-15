<?php

namespace App\Http\Requests\Penjualan\TandaTerima;

use Illuminate\Foundation\Http\FormRequest;

class StoreTandaTerimaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'penjualan_id' => [
                'required',
                'exists:penjualans,id',
            ],

            'tanggal' => [
                'required',
                'date',
            ],

            'nama_penerima' => [
                'required',
                'string',
                'max:100',
            ],

            'jabatan' => [
                'nullable',
                'string',
                'max:100',
            ],

            'catatan' => [
                'nullable',
                'string',
            ],

        ];
    }
}