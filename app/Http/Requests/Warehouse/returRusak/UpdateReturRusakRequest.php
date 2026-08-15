<?php

namespace App\Http\Requests\Warehouse\ReturRusak;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReturRusakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'details' => [
                'required',
                'array',
                'min:1',
            ],

            'details.*.id' => [
                'required',
                'exists:retur_rusak_details,id',
            ],

            'details.*.qty' => [
                'required',
                'numeric',
                'min:0',
            ],

            'details.*.alasan' => [
                'nullable',
                'string',
            ],
        ];
    }
}