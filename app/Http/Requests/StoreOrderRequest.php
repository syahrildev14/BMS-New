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
     * Dapur picks the item, quantity and supplier; an empty supplier falls
     * back to the item's default supplier. Price stays the koperasi's call
     * (see OrderController::resolveOrderLines()). Shared with
     * OrderController::addItems() so both entry points validate the same way.
     *
     * @return array<string, mixed>
     */
    public static function itemLineRules(): array
    {
        $request = request();

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'bail', 'required', 'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($request): void {
                    $item = Item::find($value);

                    if (! $item || ! $item->is_active) {
                        $fail('Barang yang dipilih tidak ditemukan atau sudah tidak aktif.');

                        return;
                    }

                    $index = (int) explode('.', $attribute)[1];

                    if (! $item->default_supplier_id && ! $request->input("items.{$index}.supplier_id")) {
                        $fail("Pilih supplier untuk barang {$item->name}.");
                    }
                },
            ],
            'items.*.supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'items.*.qty_ordered' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
        ];
    }
}
