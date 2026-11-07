<?php

namespace App\Http\Requests;

use App\Models\Item;
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
            ...self::itemLineRules(),
        ];
    }

    /**
     * Dapur only picks the item and quantity — supplier and price are the
     * koperasi's call, filled in server-side from the item master (see
     * OrderController::resolveOrderLines()). Shared with
     * OrderController::addItems() so both entry points validate the same way.
     *
     * @return array<string, mixed>
     */
    public static function itemLineRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'bail', 'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $item = Item::find($value);

                    if (! $item || ! $item->is_active) {
                        $fail('Barang yang dipilih tidak ditemukan atau sudah tidak aktif.');

                        return;
                    }

                    if (! $item->default_supplier_id) {
                        $fail("Barang {$item->name} belum punya supplier default, hubungi koperasi.");
                    }
                },
            ],
            'items.*.qty_ordered' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
