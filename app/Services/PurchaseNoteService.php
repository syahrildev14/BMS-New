<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\DailyPrice;
use App\Models\Gudang;
use App\Models\Item;
use App\Models\OrderItem;
use App\Models\PurchaseNote;
use App\Models\PurchaseNoteItem;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Nota Pembelian (purchase receipts) and the dry-goods shopping list.
 *
 *  - Dry nota: goods bought directly (e.g. Saturday shopping) go into Gudang
 *    as a new FIFO lot priced at the nota price.
 *  - Wet nota: one per Tanda Terima (Supplier + Dapur + day). Its unit
 *    prices become the cost price of exactly those order lines, so two
 *    Dapur can be billed different prices for the same item on the same day.
 *    Daily price + item master are bumped to the latest nota price as the
 *    estimate reference for the next order.
 */
class PurchaseNoteService
{
    private const BILLED_MESSAGE = 'Invoice atau tagihan supplier untuk barang di nota ini sudah terbit, harganya tidak bisa diubah lagi.';

    /** Largest value of the DECIMAL(12,2) subtotal and total columns. */
    private const MAX_AMOUNT = 9999999999.99;

    public function __construct(
        private readonly StockService $stockService,
        private readonly BillingGuard $billing,
    ) {}

    /**
     * What still has to be bought for dry goods: every DRY_GOODS order line
     * not yet on a delivery document, for Orders delivering on or before
     * $until, minus what Gudang already holds.
     *
     * @return Collection<int, array{item_id: int, sku: string, item_name: string, satuan: ?string, needed: float, stock: float, to_buy: float, supplier_name: ?string, last_cost_price: float}>
     */
    public function shoppingList(string $until): Collection
    {
        $needs = OrderItem::query()
            ->whereNull('surat_jalan_id')
            ->whereNotIn('id', AuditLog::allPendingOrderItemIds())
            ->whereHas('item', fn ($query) => $query->where('category', 'DRY_GOODS'))
            ->whereHas('order', fn ($query) => $query
                ->whereNotIn('status', ['CANCELLED', 'COMPLETED'])
                ->whereDate('delivery_date', '<=', $until))
            ->selectRaw('item_id, SUM(qty_ordered) as needed')
            ->groupBy('item_id')
            ->pluck('needed', 'item_id');

        if ($needs->isEmpty()) {
            return collect();
        }

        $gudang = Gudang::query()->firstOrFail();

        return Item::with(['defaultSupplier', 'satuan'])
            ->whereIn('id', $needs->keys())
            ->get()
            ->map(function (Item $item) use ($needs, $gudang) {
                $needed = round((float) $needs[$item->id], 2);
                $stock = round($this->stockService->availableStock($gudang->id, $item->id), 2);

                return [
                    'item_id' => $item->id,
                    'sku' => $item->sku,
                    'item_name' => $item->name,
                    'satuan' => $item->satuan?->name,
                    'needed' => $needed,
                    'stock' => $stock,
                    'to_buy' => max(0.0, round($needed - $stock, 2)),
                    'supplier_name' => $item->defaultSupplier?->name,
                    'last_cost_price' => (float) $item->base_cost_price,
                ];
            })
            ->sortBy([['to_buy', 'desc'], ['item_name', 'asc']])
            ->values();
    }

    /**
     * @param  array{supplier_id: int, note_date: string, reference_number?: ?string, notes?: ?string, items: array<int, array{item_id: int, qty: float|string, unit_price: float|string}>}  $data
     */
    public function createDryNote(array $data, User $user): PurchaseNote
    {
        return DB::transaction(function () use ($data, $user) {
            $note = $this->createWithUniqueNumber([
                'category' => 'DRY_GOODS',
                'supplier_id' => $data['supplier_id'],
                'note_date' => $data['note_date'],
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->applyDryLines($note, $data['items'], $user);

            return $note->refresh();
        });
    }

    /**
     * @param  array{reference_number?: ?string, notes?: ?string, items: array<int, array{order_item_id: int, qty: float|string, unit_price: float|string}>}  $data
     */
    public function createWetNote(SuratJalan $tandaTerima, array $data, User $user): PurchaseNote
    {
        return DB::transaction(function () use ($tandaTerima, $data, $user) {
            $tandaTerima = SuratJalan::whereKey($tandaTerima->id)->lockForUpdate()->firstOrFail();

            if ($tandaTerima->document_type !== SuratJalan::TANDA_TERIMA) {
                throw ValidationException::withMessages(['surat_jalan_id' => 'Nota basah hanya bisa dibuat dari Tanda Terima bahan basah.']);
            }

            if (PurchaseNote::where('surat_jalan_id', $tandaTerima->id)->exists()) {
                throw ValidationException::withMessages(['surat_jalan_id' => "Tanda Terima {$tandaTerima->sj_number} sudah punya nota."]);
            }

            // The lines may already be billed from a manual daily price.
            $lines = $tandaTerima->orderItems()->get(['order_items.id', 'order_id', 'supplier_id']);

            if ($this->billing->hasFinalBilling($lines)) {
                throw ValidationException::withMessages(['note' => self::BILLED_MESSAGE]);
            }

            $this->billing->voidDrafts($lines);

            $note = $this->createWithUniqueNumber([
                'category' => 'WET_GOODS',
                'supplier_id' => $tandaTerima->supplier_id,
                'surat_jalan_id' => $tandaTerima->id,
                'dapur_id' => $tandaTerima->dapur_id,
                'note_date' => $tandaTerima->shipment_date->toDateString(),
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user->id,
            ]);

            $this->applyWetLines($note, $tandaTerima, $data['items'], $user);

            return $note->refresh();
        });
    }

    /**
     * Correct a nota. Refused once the money side is final (an ISSUED/PAID
     * invoice covers its lines) or, for dry goods, once its stock has been
     * (partly) shipped out — a lot can't be re-priced after FIFO used it.
     *
     * @param  array<string, mixed>  $data  same shape as create*Note()
     */
    public function updateNote(PurchaseNote $note, array $data, User $user): PurchaseNote
    {
        return DB::transaction(function () use ($note, $data, $user) {
            $note = PurchaseNote::whereKey($note->id)->lockForUpdate()->firstOrFail();
            $before = $note->load('items')->toArray();

            // Same row lock as StockService::consumeFifo(), so a shipment
            // can't consume these lots between the check and the delete.
            StockLot::whereIn('purchase_note_item_id', $note->items()->pluck('id'))->lockForUpdate()->get();

            $this->assertEditable($note);

            $note->update([
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ] + ($note->category === 'DRY_GOODS' ? [
                'supplier_id' => $data['supplier_id'],
                'note_date' => $data['note_date'],
            ] : []));

            if ($note->category === 'DRY_GOODS') {
                $itemIds = $note->items()->pluck('id');
                StockMovement::where('reference_type', PurchaseNoteItem::class)->whereIn('reference_id', $itemIds)->delete();
                StockLot::whereIn('purchase_note_item_id', $itemIds)->delete();
                $note->items()->delete();

                $this->applyDryLines($note, $data['items'], $user);
            } else {
                $this->billing->voidDrafts($note->suratJalan->orderItems()->get(['order_id', 'supplier_id']));

                // Undo this nota's prices: a line the correction reports as
                // undelivered must not keep a price it no longer has a nota for.
                OrderItem::whereIn('id', $note->items()->where('qty', '>', 0)->whereNotNull('order_item_id')->select('order_item_id'))
                    ->update(['final_cost_price' => null, 'final_selling_price' => null]);

                $note->items()->delete();
                $this->applyWetLines($note, $note->suratJalan, $data['items'], $user);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'PURCHASE_NOTE_UPDATED',
                'description' => "Nota {$note->note_number} dikoreksi.",
                'old_data' => $before,
                'new_data' => $note->refresh()->load('items')->toArray(),
            ]);

            return $note;
        });
    }

    public function editBlockReason(PurchaseNote $note): ?string
    {
        if ($note->category === 'DRY_GOODS') {
            $consumed = StockLot::whereIn('purchase_note_item_id', $note->items()->pluck('id'))
                ->whereColumn('qty_remaining', '<', 'qty_received')
                ->exists();

            return $consumed ? 'Stok dari nota ini sudah terpakai untuk pengiriman, nota tidak bisa dikoreksi lagi.' : null;
        }

        $orderItems = OrderItem::whereIn('id', $note->items()->pluck('order_item_id')->filter())->get(['id', 'order_id', 'supplier_id']);

        return $this->billing->hasFinalBilling($orderItems) ? self::BILLED_MESSAGE : null;
    }

    private function assertEditable(PurchaseNote $note): void
    {
        $reason = $this->editBlockReason($note);

        if ($reason !== null) {
            throw ValidationException::withMessages(['note' => $reason]);
        }
    }

    /**
     * @param  array<int, array{item_id: int, qty: float|string, unit_price: float|string}>  $lines
     */
    private function applyDryLines(PurchaseNote $note, array $lines, User $user): void
    {
        $gudang = Gudang::query()->firstOrFail();
        $items = Item::whereIn('id', collect($lines)->pluck('item_id'))->get()->keyBy('id');
        $total = 0.0;

        foreach ($lines as $index => $line) {
            $item = $items[(int) $line['item_id']] ?? null;

            if (! $item || $item->category !== 'DRY_GOODS') {
                throw ValidationException::withMessages(["items.{$index}.item_id" => 'Nota kering hanya untuk barang kategori bahan kering.']);
            }

            $this->assertLineAmount($index, (float) $line['qty'], (float) $line['unit_price']);

            $noteItem = $this->createLine($note, $item, (float) $line['qty'], (float) $line['unit_price']);
            $total += (float) $noteItem->subtotal;

            $this->stockService->receiveFromNote($noteItem, $gudang, $user);
            $this->updateMasterPrice($item, (float) $noteItem->unit_price, $note);
        }

        $this->assertTotal($total);
        $note->update(['total_amount' => round($total, 2), 'has_discrepancy' => false]);
    }

    /**
     * Every line of the Tanda Terima must be present exactly once — the nota
     * is checked line by line against what was ordered.
     *
     * @param  array<int, array{order_item_id: int, qty: float|string, unit_price: float|string}>  $lines
     */
    private function applyWetLines(PurchaseNote $note, SuratJalan $tandaTerima, array $lines, User $user): void
    {
        $orderItems = $tandaTerima->orderItems()->with('item')->lockForUpdate()->get()->keyBy('id');
        $submittedIds = collect($lines)->pluck('order_item_id')->map(fn ($id) => (int) $id);

        if ($submittedIds->duplicates()->isNotEmpty() || $submittedIds->sort()->values()->all() !== $orderItems->keys()->sort()->values()->all()) {
            throw ValidationException::withMessages(['items' => "Isi nota harus mencakup tepat semua barang di Tanda Terima {$tandaTerima->sj_number}."]);
        }

        $total = 0.0;
        $hasDiscrepancy = false;

        foreach ($lines as $index => $line) {
            $orderItem = $orderItems[(int) $line['order_item_id']];
            $item = $orderItem->item;
            $qty = (float) $line['qty'];
            $unitPrice = (float) ($line['unit_price'] ?? 0);

            // Qty 0 = the supplier didn't deliver this line, so it needs no price.
            if ($qty > 0) {
                $this->assertLineAmount($index, $qty, $unitPrice);
            }

            $noteItem = $this->createLine($note, $item, $qty, $unitPrice, $orderItem->id);
            $total += (float) $noteItem->subtotal;
            $hasDiscrepancy = $hasDiscrepancy || round((float) $noteItem->qty, 2) !== round((float) $orderItem->qty_ordered, 2);

            // An undelivered line says nothing about its price or the market's:
            // a price of 0 would bill any quantity the Dapur did receive at 0.
            if ($qty <= 0) {
                continue;
            }

            $sellingPrice = round($item->calculateSellingPrice($unitPrice), 2);

            $orderItem->update([
                'final_cost_price' => $unitPrice,
                'final_selling_price' => $sellingPrice,
            ]);

            DailyPrice::updateOrCreate(
                ['item_id' => $item->id, 'date' => $note->note_date->toDateString()],
                ['cost_price' => $unitPrice, 'calculated_price' => $sellingPrice, 'created_by' => $user->id],
            );

            $this->updateMasterPrice($item, $unitPrice, $note);
        }

        $this->assertTotal($total);
        $note->update(['total_amount' => round($total, 2), 'has_discrepancy' => $hasDiscrepancy]);
    }

    private function createLine(PurchaseNote $note, Item $item, float $qty, float $unitPrice, ?int $orderItemId = null): PurchaseNoteItem
    {
        return PurchaseNoteItem::create([
            'purchase_note_id' => $note->id,
            'item_id' => $item->id,
            'order_item_id' => $orderItemId,
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'subtotal' => round($qty * $unitPrice, 2),
        ]);
    }

    private function assertLineAmount(int $index, float $qty, float $unitPrice): void
    {
        if ($unitPrice <= 0) {
            throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Harga satuan wajib diisi.']);
        }

        if ($qty * $unitPrice > self::MAX_AMOUNT) {
            throw ValidationException::withMessages(["items.{$index}.unit_price" => 'Subtotal baris ini terlalu besar, periksa qty dan harga.']);
        }
    }

    private function assertTotal(float $total): void
    {
        if ($total > self::MAX_AMOUNT) {
            throw ValidationException::withMessages(['items' => 'Total nota terlalu besar, periksa qty dan harga.']);
        }
    }

    /**
     * Latest nota price becomes the catalog estimate for the next orders.
     * "Latest" is by nota date: entering or correcting an older nota must not
     * move the catalog price backward.
     */
    private function updateMasterPrice(Item $item, float $costPrice, PurchaseNote $note): void
    {
        $hasNewerNota = PurchaseNoteItem::where('item_id', $item->id)
            ->where('qty', '>', 0)
            ->whereHas('purchaseNote', fn ($query) => $query
                ->whereKeyNot($note->id)
                ->whereDate('note_date', '>', $note->note_date->toDateString()))
            ->exists();

        if ($hasNewerNota) {
            return;
        }

        $item->update([
            'base_cost_price' => $costPrice,
            'base_selling_price' => round($item->calculateSellingPrice($costPrice), 2),
        ]);
    }

    /**
     * NOTA/{YYYYMMDD}/{nn} — daily sequence by Jakarta date, retried on a
     * duplicate-key race.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function createWithUniqueNumber(array $attributes): PurchaseNote
    {
        $maxAttempts = 5;
        $prefix = 'NOTA/'.now('Asia/Jakarta')->format('Ymd').'/';

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $sequence = PurchaseNote::where('note_number', 'like', "{$prefix}%")->count() + $attempt;

            try {
                return PurchaseNote::create($attributes + [
                    'note_number' => sprintf('%s%02d', $prefix, $sequence),
                ]);
            } catch (QueryException $e) {
                if ((int) $e->getCode() !== 23000 || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor nota unik setelah beberapa percobaan.');
    }
}
