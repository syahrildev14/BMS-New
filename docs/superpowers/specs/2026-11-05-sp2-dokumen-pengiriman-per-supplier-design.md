# Sub-proyek 2 — Dokumen Pengiriman per Supplier (SJ Kering & TT Basah)

**Tanggal:** 2026-11-05
**Status:** Disetujui (desain)

## Alur bisnis yang diikuti
- Dapur membuat order **harian**. Bahan kering sering dikirim **serentak hari Minggu** untuk beberapa hari sekaligus, kadang di hari H.
- **Surat Jalan (SJ)** hanya untuk bahan kering yang dikirim koperasi; **1 SJ per Dapur per supplier** (mis. KOP MERAH PUTIH), boleh mencakup beberapa order.
- **Tanda Terima (TT)** bahan basah diterbitkan koperasi **setiap hari, per supplier per Dapur**, sebelum dikirim; supplier membawanya, Dapur menandatangani, lalu Dapur input STT.
- Bahan kering juga disertai Tanda Terima (halaman kedua PDF SJ).

## Pendekatan
Perluas tabel `surat_jalans` menjadi dokumen pengiriman generik; `order_items.surat_jalan_id` tetap jadi tautan item → dokumen sehingga STT, guard invoice, split invoice, supplier bill, dan anti-duplikasi tetap bekerja.

## Data
Migration pada `surat_jalans`:
- `document_type` enum(`SURAT_JALAN`, `TANDA_TERIMA`), default `SURAT_JALAN`.
- `dapur_id` FK nullable → `dapurs` (wajib untuk dokumen baru; nullable hanya untuk backfill aman).
- `supplier_id` FK nullable → `suppliers` (null untuk dokumen lama campuran).
- `shipment_date` DATE nullable.
- `order_id` → nullable (dokumen baru tidak mengisi; dokumen lama tetap).
- Backfill: `dapur_id` dan `shipment_date` dari order terkait, `document_type = SURAT_JALAN`.
- `type` (`PRIMARY`/`ADDON`) & `sequence_number` dipertahankan: dokumen pertama untuk (dapur, supplier, document_type, shipment_date) = `PRIMARY`, berikutnya `ADDON`.

Relasi:
- `SuratJalan` belongsTo `Dapur`, `Supplier`; `orders()` = order unik dari `orderItems`.
- `Order::suratJalans()` = belongsToMany via `order_items` (distinct), menggantikan hasMany via `order_id`.

## Service (`DeliveryNoteService`)
- `pendingGroups(int $dapurId, string $documentType, string $date)`: daftar item siap kirim dikelompokkan per supplier.
  - `SURAT_JALAN`: item `DRY_GOODS`, order milik dapur, `delivery_date <= $date`.
  - `TANDA_TERIMA`: item `WET_GOODS`, `delivery_date = $date`.
  - Selalu: `surat_jalan_id IS NULL`, bukan pending Tier 2, order status bukan `DRAFT`/`CANCELLED`/`COMPLETED`.
- `generateDocuments(int $dapurId, string $documentType, string $date, int $userId, ?int $supplierId = null)`:
  - Dalam `DB::transaction()`; kandidat di-`lockForUpdate()`.
  - Kering: `checkStockShortfalls` per order terkait (draft PO); partisi stok FIFO; item stok kurang ditahan; konsumsi FIFO + `final_cost_price`/`final_selling_price` seperti sebelumnya.
  - Satu dokumen per supplier (atau hanya `$supplierId`).
  - Order `SUBMITTED` yang itemnya masuk dokumen → `LOCKED`.
  - Hasil: `documents`, `purchase_orders`, `unresolved_shortfalls`, `held_items`.
  - Kosong → `RuntimeException` dengan pesan jelas.
- Nomor: `SJ/{YYYYMMDD}/{DAPUR}/{SUPP}/{nn}` dan `TT/{YYYYMMDD}/{DAPUR}/{SUPP}/{nn}`; `nn` urutan harian per (dapur, document_type); retry pada duplicate key.

## Ops UI (`ops/delivery-notes`)
- Form: Dapur, Jenis (Kering → label "kirim semua order s.d. tanggal"; Basah → "tanggal kirim"), tombol Tampilkan.
- Tabel per supplier: item, order, tanggal kirim order, qty; tombol **Generate** per supplier dan **Generate semua**. Tombol disabled saat proses.
- Daftar dokumen terbit: nomor, jenis, dapur, supplier, tanggal, unduh PDF dokumen & PDF Tanda Terima hasil STT.
- Daftar item kering macet (tanpa default supplier) tetap ditampilkan.

## PDF
- `pdf/surat-jalan.blade.php`: halaman 1 Surat Jalan (item, qty, satuan, nomor order), halaman 2 Tanda Terima kosong (kolom qty diterima/ditolak, tanda tangan).
- `TANDA_TERIMA`: satu halaman Tanda Terima (form kosong untuk ditandatangani).
- Tanda Terima hasil STT (`tanda_terima_pdf_url`) tetap dibuat setelah STT.
- PDF gabungan per order (`GenerateOrderSuratJalanPdfJob`, `GenerateOrderTandaTerimaPdfJob`) tidak lagi dipakai untuk dokumen baru; Dapur mengunduh per dokumen.

## Dapur
- Halaman detail order menampilkan dokumen terkait order (via order_items) dengan tombol unduh.
- Route unduh baru `dapur.documents.download` / `dapur.documents.tanda-terima.download`, dibatasi `dapur_id` dokumen = dapur user.

## Pengujian
- SJ kering menggabungkan beberapa order dapur yang sama, dipisah per supplier.
- Basah tidak pernah masuk SJ; TT basah hanya untuk tanggal yang dipilih, per supplier.
- Stok kering kurang → item ditahan, draft PO dibuat.
- Order `SUBMITTED` → `LOCKED` saat item masuk dokumen.
- Generate dua kali → tidak ada item ganda/dokumen kosong.
- Dapur lain tidak bisa unduh dokumen.
- Item pending Tier 2 tidak ikut.
- STT & invoice tetap jalan dengan dokumen baru.

## Dokumentasi
Perbarui `CLAUDE.md` §3.1 dan PRD Module 3.
