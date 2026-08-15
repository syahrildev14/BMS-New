<?php

namespace App\Http\Requests\Master\Gudang;

use Illuminate\Foundation\Http\FormRequest;

class StoreGudangRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_gudang' => [
                'required',
                'string',
                'max:150'
            ],

            'alamat_gudang' => [
                'required',
                'string'
            ],

            'nama_pic' => [
                'required',
                'string',
                'max:100'
            ],

            'no_telepon_pic' => [
                'required',
                'string',
                'max:20',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_gudang.required' => 'Nama gudang wajib diisi.',
            'alamat_gudang.required' => 'Alamat gudang wajib diisi.',
            'nama_pic.required' => 'Nama PIC wajib diisi.',
            'no_telepon_pic.required' => 'Nomor telepon PIC wajib diisi.',
        ];
    }
}
