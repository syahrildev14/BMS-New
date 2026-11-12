<?php

namespace App\Services;

use App\Jobs\GenerateSuratJalanPdfJob;
use App\Models\AuditLog;
use App\Models\DailyPrice;
use App\Models\Dapur;
use App\Models\Gudang;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockLot;
use App\Models\SuratJalan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeliveryNoteService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly DocumentTemplateService $templates,
    ) {}

    /**
     * Decides which DRY_GOODS OrderItem rows the Gudang's current stock can
     * fully cover, and which it can't — accounting for multiple sibling
     * rows of the *same* item in the same batch (e.g. a base order line plus
     * a same-day add-on for the same item). A naive per-row
     * StockService::availableStock() re-query would let every sibling row
     * believe the same units of stock are theirs, since it never subtracts
     * what an earlier row in the same batch already claimed. Rows are
     * claimed by earliest Order delivery_date first, then ascending id
     * (submission order) — a weekly shipment spans several Orders, and the
     * soonest-needed lines must get the stock first.
     *
     * @param  Collection<int, OrderItem>  $orderItems
     * @return array{sufficient: Collection<int, OrderItem>, insufficient: Collection<int, OrderItem>}
     */
    private function partitionByStock(Collection $orderItems, int $gudangId): array
    {
        // Row-locked so another Dapur's shipment of the same item waits here
        // and then reads what is really left, instead of failing in FIFO.
        $remainingByItem = StockLot::where('gudang_id', $gudangId)
            ->whereIn('item_id', $orderItems->pluck('item_id')->unique())
            ->where('qty_remaining', '>', 0)
            ->orderBy('item_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->groupBy('item_id')
            ->map(fn (Collection $lots) => round((float) $lots->sum('qty_remaining'), 2))
            ->all();

        $sufficient = collect();
        $insufficient = collect();

        $ordered = $orderItems->sortBy([
            fn (OrderItem $a, OrderItem $b) => $a->order->delivery_date <=> $b->order->delivery_date,
            fn (OrderItem $a, OrderItem $b) => $a->id <=> $b->id,
        ]);

        foreach ($ordered as $orderItem) {
            $itemId = $orderItem->item_id;

            $remaining = $remainingByItem[$itemId] ?? 0.0;
            $needed = (float) $orderItem->qty_ordered;

            // Same 0.0001 tolerance as StockService::deductFromLots(); rounded
            // because 0.3 - 0.1 is 0.19999... in floating point.
            if ($needed <= $remaining + 0.0001) {
                $remainingByItem[$itemId] = round($remaining - $needed, 2);
                $sufficient->push($orderItem);
            } else {
                $insufficient->push($orderItem);
            }
        }

        return ['sufficient' => $sufficient, 'insufficient' => $insufficient];
    }

    /**
     * Order items ready to go on a new delivery document for one Dapur,
     * grouped by supplier_id.
     *
     *  - SURAT_JALAN  (dry goods, shipped by the koperasi): every unshipped
     *    DRY_GOODS line of the Dapur's Orders delivering on or before $date,
     *    so one weekly Sunday shipment can cover several daily Orders.
     *  - TANDA_TERIMA (wet goods, delivered by the Supplier): WET_GOODS lines
     *    of the Dapur's Orders delivering exactly on $date.
     *
     * Tier 2 lines still awaiting Ops approval are never included.
     *
     * @return Collection<int, Collection<int, OrderItem>> keyed by supplier_id
     */
    public function pendingGroups(int $dapurId, string $documentType, string $date): Collection
    {
        return $this->candidateQuery($dapurId, $documentType, $date)
            ->with(['item.satuan', 'order', 'supplier'])
            ->get()
            ->groupBy('supplier_id');
    }

    /**
     * Issue ONE delivery document for the Dapur's pending lines of every
     * supplier (see pendingGroups() for which lines): one Surat Jalan / one
     * Tanda Terima per Dapur per shipment date, not one per supplier.
     * DRY_GOODS lines are gated on Gudang stock (FIFO): a line the stock
     * cannot fully cover is held back (stays surat_jalan_id NULL) until it is
     * bought (Nota Pembelian) — it shows up on Purchasing's Daftar Belanja.
     *
     * @param  array{document_template_id?: ?int, armada_id?: ?int, driver_id?: ?int}  $options
     * @return array{documents: Collection<int, SuratJalan>, held_items: Collection<int, OrderItem>}
     */
    public function generateDocuments(int $dapurId, string $documentType, string $date, int $userId, array $options = []): array
    {
        if (! in_array($documentType, [SuratJalan::SURAT_JALAN, SuratJalan::TANDA_TERIMA], true)) {
            throw new \InvalidArgumentException("Jenis dokumen tidak dikenal: {$documentType}.");
        }

        $result = DB::transaction(function () use ($dapurId, $documentType, $date, $userId, $options) {
            // Serializes all document generation for this Dapur, which is what
            // keeps PRIMARY/ADDON and the daily number sequence correct under
            // concurrent requests. Must stay the first query: a plain read
            // before it would fix the REPEATABLE READ snapshot pre-lock, and
            // the PRIMARY/ADDON count would miss what the lock waited for.
            $dapur = Dapur::whereKey($dapurId)->lockForUpdate()->firstOrFail();
            $user = User::findOrFail($userId);
            $templateAttributes = $this->templates->deliverySelection($documentType, $options);

            $candidates = $this->candidateQuery($dapurId, $documentType, $date)
                ->with(['item', 'order', 'supplier'])
                ->lockForUpdate()
                ->get();

            // Same row lock as SplitInvoiceService::approveInvoice(): an Order
            // closed after the query above must not ship or consume stock.
            $openOrderIds = Order::whereIn('id', $candidates->pluck('order_id')->unique())
                ->lockForUpdate()
                ->get()
                ->reject(fn (Order $order) => in_array($order->status, ['CANCELLED', 'COMPLETED'], true))
                ->pluck('id');

            $candidates = $candidates->whereIn('order_id', $openOrderIds)->values();

            $toAssign = $candidates;
            $heldItems = collect();
            $gudang = null;

            if ($toAssign->isEmpty()) {
                throw new \RuntimeException('Tidak ada item yang perlu dibuatkan dokumen pengiriman untuk pilihan ini (atau seluruhnya masih menunggu persetujuan revisi Tier 2).');
            }

            if ($documentType === SuratJalan::SURAT_JALAN) {
                // Stock is judged across ALL Orders in this shipment at once
                // (earliest delivery first) — per-Order checks would let two
                // Orders each believe the same stock is theirs.
                $gudang = Gudang::query()->firstOrFail();
                $partition = $this->partitionByStock($candidates, $gudang->id);
                $toAssign = $partition['sufficient']->values();
                $heldItems = $partition['insufficient']->values();
            }

            $documents = collect();

            // Everything held back for stock: nothing to put on a document.
            if ($toAssign->isNotEmpty()) {
                $document = $this->createDocumentWithUniqueNumber($dapur, $documentType, $date, $userId, $templateAttributes);

                foreach ($toAssign as $orderItem) {
                    $this->attachToDocument($orderItem, $document, $user, $gudang);
                }

                $documents->push($document);
            }

            // Official LOCKED trigger: an Order locks as soon as any of its
            // lines is on a delivery document. PricingEngineService only
            // syncs daily WET_GOODS prices onto LOCKED Orders.
            Order::whereIn('id', $toAssign->pluck('order_id')->unique())
                ->whereIn('status', ['DRAFT', 'SUBMITTED'])
                ->update(['status' => 'LOCKED']);

            return ['documents' => $documents, 'held_items' => $heldItems];
        });

        // Already committed: stock is consumed and the documents exist, so a
        // PDF failure must not surface as a failed generation. Rendered again
        // on download, see ensurePdf().
        foreach ($result['documents'] as $document) {
            try {
                GenerateSuratJalanPdfJob::dispatchSync($document->id);
            } catch (Throwable $e) {
                Log::error('Gagal generate PDF dokumen pengiriman.', [
                    'surat_jalan_id' => $document->id,
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /** Renders the document PDF if it is missing, e.g. after a failed first attempt. */
    public function ensurePdf(SuratJalan $document): SuratJalan
    {
        if (! $document->pdf_url || ! Storage::disk('local')->exists($document->pdf_url)) {
            GenerateSuratJalanPdfJob::dispatchSync($document->id);
        }

        return $document->refresh();
    }

    private function candidateQuery(int $dapurId, string $documentType, string $date): Builder
    {
        $category = $documentType === SuratJalan::SURAT_JALAN ? 'DRY_GOODS' : 'WET_GOODS';

        return OrderItem::query()
            ->whereNull('surat_jalan_id')
            ->whereNotIn('id', AuditLog::allPendingOrderItemIds())
            ->whereHas('item', fn ($query) => $query->where('category', $category))
            ->whereHas('order', function ($query) use ($dapurId, $documentType, $date) {
                $query->where('dapur_id', $dapurId)
                    ->whereNotIn('status', ['CANCELLED', 'COMPLETED']);

                if ($documentType === SuratJalan::SURAT_JALAN) {
                    $query->whereDate('delivery_date', '<=', $date);
                } else {
                    $query->whereDate('delivery_date', $date);
                }
            })
            ->orderBy('id');
    }

    private function attachToDocument(OrderItem $orderItem, SuratJalan $document, User $user, ?Gudang $gudang): void
    {
        $item = $orderItem->item;
        $update = ['surat_jalan_id' => $document->id];

        if ($item->category !== 'DRY_GOODS' && $orderItem->final_selling_price === null) {
            // PricingEngineService only prices LOCKED Orders, so a daily price
            // entered before this line was on a document hasn't reached it.
            $dailyPrice = DailyPrice::where('item_id', $item->id)
                ->whereDate('date', $orderItem->order->delivery_date->toDateString())
                ->first();

            if ($dailyPrice) {
                $update['final_cost_price'] = $dailyPrice->cost_price;
                $update['final_selling_price'] = $dailyPrice->calculated_price;
            }
        }

        if ($item->category === 'DRY_GOODS') {
            $consumption = $this->stockService->consumeFifo(
                $gudang,
                $item,
                (float) $orderItem->qty_ordered,
                OrderItem::class,
                $orderItem->id,
                $user,
            );

            $update['final_cost_price'] = $consumption['weighted_unit_cost'];
            $update['final_selling_price'] = $item->calculateSellingPrice($consumption['weighted_unit_cost']);
        }

        $orderItem->update($update);
    }

    /**
     * SJ/{YYYYMMDD}/{DAPUR}/{nn} or TT/..., where nn is a daily sequence
     * (Jakarta date) per (Dapur, document type). Generation holds the Dapur
     * row lock, so this only retries on a stray duplicate key.
     * The first document for a (Dapur, type, shipment date) is PRIMARY; any
     * later one for the same combination is an ADDON. The document covers
     * every supplier, so supplier_id stays NULL (suppliers are per line).
     *
     * @param  array<string, int|string>  $templateAttributes
     */
    private function createDocumentWithUniqueNumber(Dapur $dapur, string $documentType, string $date, int $userId, array $templateAttributes): SuratJalan
    {
        $prefix = $documentType === SuratJalan::SURAT_JALAN ? 'SJ' : 'TT';

        $sameShipmentCount = SuratJalan::where('dapur_id', $dapur->id)
            ->whereNull('order_id')
            ->where('document_type', $documentType)
            ->whereDate('shipment_date', $date)
            ->count();

        $maxAttempts = 5;
        $datePrefix = sprintf('%s/%s/%s/', $prefix, now('Asia/Jakarta')->format('Ymd'), $dapur->code);

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $dailySequence = SuratJalan::where('sj_number', 'like', "{$datePrefix}%")->count() + $attempt;

            try {
                return SuratJalan::create($templateAttributes + [
                    'sj_number' => sprintf('%s%02d', $datePrefix, $dailySequence),
                    'document_type' => $documentType,
                    'dapur_id' => $dapur->id,
                    'supplier_id' => null,
                    'shipment_date' => $date,
                    'type' => $sameShipmentCount === 0 ? 'PRIMARY' : 'ADDON',
                    'sequence_number' => $sameShipmentCount + 1,
                    'printed_by' => $userId,
                ]);
            } catch (QueryException $e) {
                $isDuplicateKey = (int) $e->getCode() === 23000;

                if (! $isDuplicateKey || $attempt === $maxAttempts) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Gagal membuat nomor dokumen pengiriman unik setelah beberapa percobaan.');
    }
}
