<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->dapur_id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'items.*.qty_ordered' => ['required', 'numeric', 'min:0.01'],
            'items.*.estimated_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
