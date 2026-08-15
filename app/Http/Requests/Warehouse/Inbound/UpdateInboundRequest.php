<?php

namespace App\Http\Requests\Warehouse\Inbound;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInboundRequest extends FormRequest
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
                'exists:inbound_details,id',
            ],

            'details.*.qty_diterima' => [
                'required',
                'numeric',
                'min:0',
            ],

        ];
    }
}
