<?php

namespace App\Http\Requests\Master\Armada;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateArmadaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_unit' => [
                'required',
                'string',
                'max:100'
            ],

            'nomor_polisi' => [
                'required',
                'string',
                'max:20',
                Rule::unique('armadas', 'nomor_polisi')->ignore($this->armada),
            ],

            'jenis_kendaraan' => [
                'required',
                Rule::in(['roda_2', 'roda_4'])
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_unit.required' => 'Nama unit wajib diisi.',
            'nomor_polisi.required' => 'Nomor polisi wajib diisi.',
            'nomor_polisi.unique' => 'Nomor polisi sudah digunakan.',
            'jenis_kendaraan.required' => 'Jenis kendaraan wajib dipilih.',
            'jenis_kendaraan.in' => 'Jenis kendaraan tidak valid.',
        ];
    }
}