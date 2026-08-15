<?php

namespace App\Http\Requests\Penjualan;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePenjualanRequest extends FormRequest
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
                'exists:penjualan_details,id',
            ],

            'details.*.qty' => [
                'required',
                'numeric',
                'min:0.01',
            ],

            'details.*.harga' => [
                'required',
                'numeric',
                'min:0',
            ],

        ];
    }
}