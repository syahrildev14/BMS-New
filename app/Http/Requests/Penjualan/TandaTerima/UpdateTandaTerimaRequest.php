<?php

namespace App\Http\Requests\Penjualan\TandaTerima;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTandaTerimaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

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