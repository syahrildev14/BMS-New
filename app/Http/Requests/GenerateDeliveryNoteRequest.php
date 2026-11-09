<?php

namespace App\Http\Requests;

use App\Models\SuratJalan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateDeliveryNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dapur_id' => ['required', 'integer', 'exists:dapurs,id'],
            'document_type' => ['required', Rule::in([SuratJalan::SURAT_JALAN, SuratJalan::TANDA_TERIMA])],
            'date' => ['required', 'date_format:Y-m-d'],
            // Null = one document for every supplier in the pending list.
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'document_template_id' => ['nullable', 'integer'],
            'armada_id' => ['nullable', 'integer'],
            'driver_id' => ['nullable', 'integer'],
        ];
    }
}
