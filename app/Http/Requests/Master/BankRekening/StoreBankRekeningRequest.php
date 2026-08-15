<?php

namespace App\Http\Requests\Master\BankRekening;

use Illuminate\Foundation\Http\FormRequest;

class StoreBankRekeningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nama_bank' => ['required', 'string', 'max:100'],

            'nomor_rekening' => [
                'required',
                'string',
                'max:50',
                'unique:bank_rekenings,nomor_rekening'
            ],

            'atas_nama' => ['required', 'string', 'max:150'],

            'cabang_bank' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_bank.required' => 'Nama bank wajib diisi.',
            'nomor_rekening.required' => 'Nomor rekening wajib diisi.',
            'nomor_rekening.unique' => 'Nomor rekening sudah digunakan.',
            'atas_nama.required' => 'Atas nama wajib diisi.',
            'cabang_bank.required' => 'Cabang bank wajib diisi.',
        ];
    }
}