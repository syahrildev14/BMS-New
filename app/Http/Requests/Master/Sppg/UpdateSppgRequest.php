<?php

namespace App\Http\Requests\Master\Sppg;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSppgRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_sppg' => [
                'required',
                'string',
                'max:150',
            ],

            'kode_sppg' => [
                'required',
                'string',
                'max:30',
                Rule::unique('sppgs', 'kode_sppg')->ignore($this->sppg),
            ],

            'alamat_sppg' => [
                'required',
                'string',
            ],

            'mitra_id' => [
                'required',
                'exists:mitras,id'
            ],

            'penanggung_jawab' => [
                'required',
                'string',
                'max:100'
            ],

            'nomor_telepon' => [
                'required',
                'string',
                'max:20'
            ],
        ];
    }
}