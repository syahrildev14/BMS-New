# Sub-proyek 3+4 — Daftar Belanja Kering & Nota Pembelian

**Tanggal:** 2026-11-05
**Status:** Disetujui (desain)

## Keputusan bisnis
- Belanja bahan kering (Sabtu) dilakukan langsung oleh staf koperasi → pulang membawa **nota**; tidak ada PO ke supplier.
- **Draft PO otomatis dihentikan**; kekurangan stok kering tampil di Daftar Belanja. Menu PO disembunyikan, data & halaman PO lama tetap ada untuk riwayat.
- Nota bahan basah: **satu nota per supplier per Dapur per hari** = satu Tanda Terima (SP2).
- Harga basah untuk penagihan diambil dari **nota masing-masing** (per baris pesanan). Harga Harian & harga master barang ikut diperbarui ke harga nota terakhir sebagai acuan estimasi.

## Data
- `purchase_notes`: id, note_number (unique, `NOTA/{YYYYMMDD}/{nn}`), reference_number (nomor nota fisik, nullable), category (`DRY_GOODS`/`WET_GOODS`), supplier_id (FK, wajib), surat_jalan_id (FK nullable, unique — wajib untuk WET), dapur_id (FK nullable — terisi untuk WET), note_date (DATE), total_amount DECIMAL(12,2), has_discrepancy (bool), notes (text nullable), created_by (FK users), timestamps.
- `purchase_note_items`: id, purchase_note_id (FK cascade), item_id (FK), order_item_id (FK nullable — WET), qty DECIMAL(10,2), unit_price DECIMAL(12,2), subtotal DECIMAL(12,2), timestamps.
- `stock_lots.purchase_note_item_id` FK nullable (nullOnDelete).
- Permission `purchase-notes.manage` (migration aditif: diberikan ke role `purchasing` & `admin` bila ada; juga di seeder).

## Service `PurchaseNoteService`
- `shoppingList(from, to)`: per item DRY_GOODS, `needed` = SUM(qty_ordered) baris yang belum punya dokumen (`surat_jalan_id IS NULL`), order bukan CANCELLED/COMPLETED, `delivery_date` dalam rentang, bukan pending Tier 2; `stock` = stok gudang; `to_buy = max(0, needed − stock)`; sertakan supplier default & harga pokok terakhir.
- `createDryNote(data, user)`: transaksi — buat nota + baris; tiap baris → `StockService::receiveFromNote()` (lot FIFO baru, movement `PURCHASE_IN` ref `PurchaseNoteItem`); `items.base_cost_price`/`base_selling_price` = harga nota.
- `createWetNote(SuratJalan tt, lines, user)`: TT harus `TANDA_TERIMA` & belum punya nota; setiap baris TT wajib ada di input (qty nota + harga). Transaksi — nota + baris (link order_item); `order_items.final_cost_price` = harga nota, `final_selling_price` = margin; `daily_prices` (item, note_date) updateOrCreate; harga master barang diperbarui; `has_discrepancy` = ada baris qty nota ≠ qty_ordered.
- `updateNote(note, data, user)`:
  - Ditolak bila ada invoice ISSUED/OVERDUE/PAID untuk (order, supplier) baris terkait (WET), atau bila lot stoknya sudah terpakai sebagian (DRY).
  - DRY: hapus lot+movement lama, buat ulang. WET: terapkan ulang harga.
- Nomor nota: urutan harian, retry pada duplicate key.

## Perubahan perilaku lain
- `RevisionGateService` tidak lagi memanggil pengecekan kekurangan stok/draft PO.
- `DeliveryNoteService::generateDocuments` tetap menahan item kering yang stoknya kurang, tanpa draft PO. Metode drafting PO dihapus.
- `PricingEngineService::processDailyPrices` tidak menimpa `order_items` yang sudah punya harga dari nota.

## UI (Purchasing)
- **Daftar Belanja Kering**: filter rentang tanggal, tabel item/kebutuhan/stok/perlu dibeli/supplier saran, tombol Cetak.
- **Nota Pembelian**: daftar nota; daftar Tanda Terima basah yang belum punya nota (tombol *Input Nota*); tombol *Nota Kering Baru*.
- Form nota kering (supplier, tanggal, nomor nota, baris barang/qty/harga).
- Form nota basah (terisi dari TT: barang, qty pesan; input qty nota & harga; tampilkan selisih).
- Edit nota memakai form yang sama.
- Menu: Purchasing → Daftar Belanja, Nota Pembelian. Menu PO Purchasing & "Terima Barang" Gudang disembunyikan.

## Pengujian
Daftar belanja; nota kering → stok; nota basah → harga per baris (dua dapur beda harga); selisih qty; harga harian manual tak menimpa harga nota; edit ditolak setelah invoice ISSUED / lot terpakai; tak ada draft PO otomatis; satu TT satu nota; akses permission.

## Di luar cakupan
Tagihan supplier (AP) berbasis nota → Sub-proyek 6.
