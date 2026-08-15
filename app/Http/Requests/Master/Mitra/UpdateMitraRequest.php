<?php

namespace App\Http\Requests\Master\Mitra;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMitraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_yayasan' => [
                'required',
                'string',
                'max:150'
            ],

            'alamat_yayasan' => [
                'required',
                'string'
            ],

            'nama_pic_yayasan' => [
                'required',
                'string',
                'max:100'
            ],

            'no_pic_yayasan' => [
                'required',
                'string',
                'max:20'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_yayasan.required' => 'Nama yayasan wajib diisi.',
            'alamat_yayasan.required' => 'Alamat yayasan wajib diisi.',
            'nama_pic_yayasan.required' => 'Nama PIC wajib diisi.',
            'no_pic_yayasan.required' => 'Nomor PIC wajib diisi.',
        ];
    }
}