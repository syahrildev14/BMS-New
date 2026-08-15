<?php

namespace App\Http\Requests\Warehouse\ReturRusak;

use Illuminate\Foundation\Http\FormRequest;

class StoreReturRusakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inbound_id' => [
                'required',
                'exists:inbounds,id',
            ],

            'tanggal' => [
                'required',
                'date',
            ],

            'jenis' => [
                'required',
                'in:retur,rusak',
            ],

            'keterangan' => [
                'nullable',
                'string',
            ],
        ];
    }
}