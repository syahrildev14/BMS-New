<?php

namespace App\Http\Requests\Master\Karyawan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKaryawanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_karyawan' => ['required','string','max:150'],

            'alamat_karyawan' => ['required','string'],

            'nomor_telepon' => ['required','string','max:20'],

            'jabatan' => ['required','string','max:100'],

            'tanggal_masuk' => ['required','date'],

            'status' => [
                'required',
                Rule::in(['aktif','nonaktif'])
            ],
        ];
    }
}