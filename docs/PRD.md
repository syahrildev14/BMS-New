# PRODUCT REQUIREMENT DOCUMENT (PRD)
## BUSINESS MANAGEMENT SYSTEM (BMS) - SPPG / MBG SUPPLY CHAIN ENGINE

- **Document Version:** 5.1.0 (Dapur-Facing Portal, Multi-Surat Jalan, Reconciliation Engine, Gudang & Purchasing Module)
- **Target Stack:** Laravel 12, Inertia.js (React), MySQL 8.0+ / SQLite (dev), TailwindCSS
- **Target Audience:** Engineering Team, Product Owner, Claude Code Agent
- **Compliance Standard:** Audit-Ready LPJ SPPG, Dapur Portal Integration, Multi-Delivery Note Reconciliation, Dynamic RBAC (Spatie)

---

## 0. CHANGELOG — v5.0.0 → v5.1.0 (2026-10-21)

Revisi ini menutup audit kesesuaian PRD v5.0.0 terhadap implementasi aktual. Ringkasan perubahan:

1. **Satuan jadi master data terpisah** — `items.unit` (string bebas) diganti `items.satuan_id` (FK ke tabel baru `satuans`) untuk menghindari typo pada input manual.
2. **Modul baru: Gudang & Purchasing (§3, Module 5)** — DRY_GOODS kini dibeli lewat Purchase Order dan disimpan di Gudang (stok FIFO) sebelum dikirim ke Dapur, bukan langsung Supplier→Dapur. WET_GOODS **tidak berubah** (tetap langsung dari Supplier, tanpa mampir gudang — sesuai sifatnya yang tidak bisa distok).
3. **Module 2 dipersempit ke WET_GOODS saja** — klarifikasi, bukan perubahan arah: ringkasan eksekutif v5.0.0 sudah menyebut "harga **bahan basah** harian", jadi Harga Harian secara definisi hanya relevan untuk barang yang tidak distok. Harga pokok DRY_GOODS sekarang berasal dari biaya aktual lot FIFO saat Surat Jalan diterbitkan (§Module 5).
4. **Kontradiksi internal Module 1 Tier 2 diperbaiki** — v5.0.0 menyebut item berubah status `PENDING_APPROVAL`, padahal skema `order_items` v5.0.0 sendiri tidak punya kolom status. Diklarifikasi: state pending/approve/reject dilacak lewat `audit_logs`, bukan kolom status baru.
5. **Status `Order.LOCKED` dipertegas pemicunya** — v5.0.0 mengasumsikan ada mekanisme yang mengunci Order ke status `LOCKED` tapi tidak pernah menentukan kapan. Dipertegas: terjadi saat Surat Jalan PRIMARY pertama kali diterbitkan untuk Order tersebut (§Module 3).
6. **Format nomor Invoice diperjelas** — `{SUPP_CODE}` pada `INV/{YYYYMMDD}/{DAPUR_CODE}/{SUPP_CODE}` ditegaskan maksudnya adalah **kolom `suppliers.code`** (mis. `SUP-BERAS`), bukan `suppliers.id` numerik — beberapa implementasi awal keliru memakai ID.
7. **Pembagian kewenangan Generate Surat Jalan dipertegas** — hanya **Tim Ops** (bukan "Ops/Finance") yang menerbitkan Surat Jalan, konsisten dengan pemisahan tugas: Ops menangani logistik pengiriman, Finance menangani penagihan.
8. **§5 (baru): Status Implementasi** — lampiran yang melacak item yang masih belum dibangun di kode saat dokumen ini ditulis, supaya PRD dan kondisi nyata sistem tidak kembali menyimpang tanpa disadari.

---

## 1. EXECUTIVE SUMMARY & ARCHITECTURE OVERVIEW

Sistem BMS v5.x memberikan akses langsung kepada **Dapur SPPG / MBG** untuk memasukkan daftar pesanan (*pre-order* dan *add-on*) secara mandiri melalui portal web. Hal ini mengoperasikan *self-service order entry* langsung dari pihak Dapur, yang didukung oleh manajemen internal (Ops, Purchasing, Finance, Gudang) di sisi backend.

### 1.1 Core Business Rules & Enhancements
1. **Dapur-Facing Portal (Direct Order Entry):** Pihak Dapur SPPG memiliki akun khusus untuk menginput daftar pesanan harian berdasarkan katalog produk aktif dan estimasi harga real-time.
2. **Flexible Multi-Tier Cut-off (Handling Revisi Siang/Sore/Malam):** Mengakomodasi perubahan menu/porsi mendadak dari Dapur dengan mekanisme *Auto-Merge*, *Pending Approval Ops*, dan *Same-Day Emergency Override*.
3. **Multi-Delivery Note Engine (Parent-Child SJ & Add-on SJ):** Mendukung penerbitan **Surat Jalan Utama (Reguler)** untuk pengiriman awal dan **Surat Jalan Susulan (Add-on SJ pada H+1/H+2)** untuk item/kuantitas tambahan tanpa membatalkan atau merevisi Surat Jalan lama yang sudah dibawa pengemudi.
4. **Daily Dynamic Pricing & Real-Time Master Sync (WET_GOODS):** Mengelola fluktuasi harga bahan basah harian via input Purchasing. Saat harga harian di-update, sistem **otomatis memperbarui data master bahan baku** agar harga katalog Pre-Order H+1 selalu presisi.
5. **Gudang & Purchasing Buffer (DRY_GOODS):** Barang kering dibeli lewat Purchase Order dan disimpan sebagai stok Gudang (FIFO) sebelum dikirim ke Dapur — mengurangi ketergantungan pada pengiriman harian Supplier untuk barang yang memang tidak perlu dibeli setiap hari.
6. **Audit-Ready Reconciled Split Billing:** Menggabungkan seluruh Surat Jalan (Utama & Susulan) dari satu `Order ID` dan memecah tagihan Invoice per *Supplier ID* berdasarkan kuantitas aktual yang lolos QC (`qty_received - qty_rejected`).

---

## 2. DATABASE SCHEMA DESIGN (MYSQL MIGRATIONS)

Struktur tabel utama untuk diimplementasikan via Laravel Migrations:

### 2.1 RBAC & User Management Tables (Spatie Integration)
- **`roles`**: id, name, guard_name, timestamps.
- **`permissions`**: id, name, guard_name, timestamps.
- **`model_has_roles`**: role_id, model_type, model_id.
- **`role_has_permissions`**: permission_id, role_id.
- **`users`**: id, name, email, password, avatar_path (string nullable), dapur_id (FK nullable - for Dapur User), supplier_id (FK nullable - for Supplier User), is_active (boolean), timestamps.

### 2.2 Master Data Tables
- **`satuans`**: id, code (unique), name, timestamps. *(v5.1.0 — satuan sekarang master data terpisah, bukan string bebas di `items.unit`.)*
- **`dapurs`**: id, code (unique), name, address, pic_name, pic_phone, timestamps.
- **`suppliers`**: id, code (unique), name, address, pic_name, pic_phone, timestamps.
- **`gudangs`** *(baru, v5.1.0)*: id, code (unique), name, address, timestamps. Saat ini hanya **satu baris** (satu gudang pusat untuk semua Dapur) — model tetap disiapkan untuk multi-lokasi di masa depan.
- **`items`**: id, sku (unique), name, category (`DRY_GOODS`, `WET_GOODS`), satuan_id (FK), default_supplier_id (FK), margin_type (`PERCENTAGE`, `FIXED_IDR`), margin_value (DECIMAL 10,2), base_cost_price (DECIMAL 12,2), base_selling_price (DECIMAL 12,2), is_active (boolean), timestamps.

### 2.3 Order, Delivery & Billing Tables
- **`daily_prices`**: id, item_id (FK, **WET_GOODS saja**), date (DATE), cost_price (DECIMAL 12,2), calculated_price (DECIMAL 12,2), created_by (FK User), timestamps. *Unique Index: (item_id, date)*.
- **`orders`**: id, order_number (unique), dapur_id (FK), delivery_date (DATE), status (`DRAFT`, `SUBMITTED`, `LOCKED`, `PROCESSING`, `DELIVERED`, `COMPLETED`, `CANCELLED`), notes, timestamps. *Transisi ke `LOCKED` lihat §Module 3.*
- **`surat_jalans`** *(dokumen pengiriman, direvisi 2026-11-05)*: id, sj_number (unique), document_type (`SURAT_JALAN`, `TANDA_TERIMA`), order_id (FK nullable, legacy), dapur_id (FK), supplier_id (FK), shipment_date (DATE), type (`PRIMARY`, `ADDON`), sequence_number (INT default 1), pdf_url, tanda_terima_pdf_url, verification_status (`PENDING`, `VERIFIED`, `DISCREPANCY`), verification_notes, verified_by (FK User nullable), verified_at, printed_by (FK User), timestamps. Lihat §Module 3 & 4.
- **`delivery_document_photos`** *(2026-11-07)*: id, surat_jalan_id (FK), path, original_name, uploaded_by (FK User), timestamps.
- **`order_items`**: id, order_id (FK), item_id (FK), supplier_id (FK - denormalized), surat_jalan_id (FK nullable - references `surat_jalans`), is_addon (boolean), qty_ordered (DECIMAL 10,2), qty_received (DECIMAL 10,2 nullable), qty_rejected (DECIMAL 10,2 nullable), rejection_reason (text nullable), estimated_price (DECIMAL 12,2), final_cost_price (DECIMAL 12,2 nullable), final_selling_price (DECIMAL 12,2 nullable), timestamps. *Tidak ada kolom status per-item — lihat §Module 1 Tier 2 untuk cara melacak status revisi.*
- **`audit_logs`**: id, user_id (FK), action, description, old_data (JSON), new_data (JSON), timestamps.
- **`invoices`**: id, invoice_number (unique), order_id (FK), dapur_id (FK), supplier_id (FK), total_amount (DECIMAL 12,2), status (`DRAFT`, `ISSUED`, `PAID`, `OVERDUE`, `VOID`), pdf_url, timestamps. *Unique Index: (order_id, supplier_id).*

### 2.4 Gudang & Purchasing Tables *(baru, v5.1.0 — lihat §Module 5)*
- **`purchase_orders`**: id, po_number (unique), supplier_id (FK), gudang_id (FK), triggered_by_order_id (FK nullable, Order yang memicu PO ini), status (`DRAFT`, `SENT`, `PARTIALLY_RECEIVED`, `RECEIVED`, `CANCELLED`), created_by (FK User), sent_by (FK User, nullable), sent_at (nullable), timestamps.
- **`purchase_order_items`**: id, purchase_order_id (FK), item_id (FK), qty_ordered (DECIMAL 10,2), qty_received (DECIMAL 10,2 default 0), unit_cost (DECIMAL 12,2), timestamps.
- **`stock_lots`**: id, gudang_id (FK), item_id (FK), purchase_order_item_id (FK nullable), qty_received (DECIMAL 10,2), qty_remaining (DECIMAL 10,2), unit_cost (DECIMAL 12,2), received_at (datetime), timestamps. *Sumber kebenaran stok: `SUM(qty_remaining)` per (gudang, item) — tidak ada tabel saldo terpisah.*
- **`purchase_notes`** *(2026-11-05, §Module 5)*: id, note_number (unique), reference_number (nullable), category (`DRY_GOODS`, `WET_GOODS`), supplier_id (FK), surat_jalan_id (FK nullable, unique), dapur_id (FK nullable), note_date (DATE), total_amount (DECIMAL 12,2), has_discrepancy (boolean), notes, created_by (FK User), timestamps.
- **`purchase_note_items`** *(2026-11-05)*: id, purchase_note_id (FK), item_id (FK), order_item_id (FK nullable), qty (DECIMAL 10,2), unit_price (DECIMAL 12,2), subtotal (DECIMAL 12,2), timestamps. `stock_lots` juga punya purchase_note_item_id (FK nullable).
- **`stock_movements`**: id, gudang_id (FK), item_id (FK), stock_lot_id (FK nullable), type (`PURCHASE_IN`, `ORDER_OUT`, `OPNAME_ADJUST`), qty (DECIMAL 10,2), reference_type, reference_id, created_by (FK User), timestamps. *Jejak audit tiap stok masuk/keluar.*

---

## 3. DETAILED FUNCTIONAL REQUIREMENTS

### MODULE 0: DYNAMIC USER & ROLE MANAGEMENT
- **Dynamic Permission Assignment:** Admin dapat mengontrol akses fitur untuk Dapur, Ops, Purchasing, Finance, Gudang, dan Admin via UI Dashboard.
- **Data Scoping Guard:**
  - Akun berpenerbit `dapur_id` otomatis dibatasi (*data scope*) hanya bisa mengakses portal dan data dapur miliknya sendiri — berlaku di daftar maupun detail Order.
- **Warna Tema (`appearance.manage`, Admin → Tampilan):** Admin menentukan satu warna utama untuk seluruh pengguna dan halaman login, tersimpan di `app_settings` (key `primary_color`). Warna harus berkontras minimal 3:1 terhadap latar putih dan gelap. Warna status dan PDF tidak ikut berubah.

### MODULE 1: DAPUR PORTAL & FLEXIBLE REVISION GATE
- **Direct Order Entry:** Pengguna dari pihak Dapur SPPG login ke portal khusus dan menginput daftar pre-order bahan baku secara mandiri menggunakan katalog item yang aktif. Per baris barang Dapur memilih **supplier** sendiri *(2026-11-12)*, otomatis terisi `default_supplier_id` barang dan boleh diganti; harga tetap dari master (harga pokok & margin tidak dikirim ke Dapur).
- **Tier 1 (Normal Cut-off - s.d. 17.00 WIB):** Dapur bebas menginput PO atau item tambahan (*add-on*). Sistem otomatis mengikat (*auto-merge*) ke `Master Order ID` dengan status `SUBMITTED`.
- **Tier 2 (Late Revision - 17.01 s.d. 22.00 WIB):** Pengajuan revisi/add-on malam hari oleh Dapur ditandai **pending approval lewat `audit_logs`** (action `ORDER_REVISION_PENDING_APPROVAL`) — bukan kolom status di `order_items`, karena skema tabel itu tidak punya kolom status (lihat §2.3). Tim Ops meninjau daftar ini dan memilih *Approve* (action `ORDER_REVISION_APPROVED`) atau *Reject* (action `ORDER_REVISION_REJECTED`, item yang diajukan dihapus).
- **Tier 3 (Same-Day Emergency - Hari H):** Tim Ops memiliki *Override Authority* untuk penyesuaian darurat di sistem saat pengiriman — berlaku sepanjang hari pengiriman (`delivery_date`), bukan dibatasi jam spesifik.
- **Audit Trail:** Seluruh aksi revisi (auto-merge, pending, approve, reject, emergency override) tercatat otomatis di tabel `audit_logs`.

### MODULE 2: DAILY DYNAMIC PRICING & AUTOMATED MASTER SYNC (WET_GOODS)
- **Cakupan:** Modul ini **hanya berlaku untuk item berkategori `WET_GOODS`**. DRY_GOODS mendapat harga pokoknya dari Gudang (§Module 5), bukan dari input harga harian.
- **Cost Price Input:** Tim Purchasing menginput harga beli harian dari supplier untuk setiap item WET_GOODS.
- **Pricing Engine & Master Sync:** Dalam satu `DB::transaction()`:
  1. Hitung harga jual harian ($\text{Cost} + \text{Margin}$) dan kunci pada `order_items` milik Order berstatus `LOCKED` untuk tanggal pengiriman yang sama.
  2. Perbarui `base_cost_price` dan `base_selling_price` di tabel `items` secara otomatis untuk estimasi katalog H+1 bagi Dapur.

### MODULE 3: DOKUMEN PENGIRIMAN PER DAPUR (SURAT JALAN KERING & TANDA TERIMA BASAH) *(direvisi 2026-11-05, 2026-11-12)*
- **Kewenangan:** Hanya **Tim Ops** yang menerbitkan dokumen pengiriman (pemisahan tugas dari Finance, §Module 4).
- **Dua jenis dokumen** (`surat_jalans.document_type`):
  - `SURAT_JALAN` — **bahan kering** yang dikirim koperasi dari Gudang. Ops memilih Dapur dan "kirim semua order s.d. tanggal X": semua item DRY_GOODS yang belum terkirim dari order-order Dapur itu (`delivery_date <= X`) digabung, **satu dokumen untuk semua supplier** *(2026-11-12; sebelumnya satu per supplier)*. Supplier tampil per baris barang. Mendukung pengiriman serentak hari Minggu untuk beberapa order harian, maupun pengiriman di hari H. PDF format bawaan berisi Surat Jalan + form Tanda Terima; dokumen yang memakai template unggahan hanya berisi halaman Surat Jalan.
  - `TANDA_TERIMA` — **bahan basah** yang dikirim langsung supplier. Diterbitkan **setiap hari**, satu per Dapur untuk `delivery_date = tanggal kirim`, berisi barang semua supplier *(2026-11-12)*. Supplier membawa dokumen ini dan Dapur menandatanganinya. WET_GOODS tidak pernah masuk Surat Jalan.
- **Tautan item:** `order_items.surat_jalan_id` menautkan item ke dokumen; satu dokumen bisa mencakup item dari beberapa Order. `surat_jalans.order_id` hanya untuk data lama.
- **PRIMARY / ADDON:** dokumen pertama untuk (Dapur, jenis, tanggal kirim) = `PRIMARY`; dokumen berikutnya untuk kombinasi yang sama (item susulan, atau item kering yang sempat ditahan) = `ADDON`.
- **Nomor:** `SJ/{YYYYMMDD}/{DAPUR_CODE}/{nn}` dan `TT/{YYYYMMDD}/{DAPUR_CODE}/{nn}`. Dokumen baru ber-`supplier_id` NULL; dokumen per supplier yang terbit sebelumnya tetap berlaku.
- **Status `LOCKED`:** Order berstatus `DRAFT`/`SUBMITTED` otomatis menjadi `LOCKED` saat salah satu itemnya pertama kali masuk dokumen pengiriman — pemicu resmi yang dirujuk Module 2. Baris basah yang belum berharga mengambil Harga Harian tanggal kirimnya saat masuk dokumen.
- **Gating Stok DRY_GOODS:** stok Gudang dinilai sekaligus untuk semua order dalam satu pengiriman, order dengan `delivery_date` paling awal mendapat stok lebih dulu. Item yang stoknya kurang **ditahan** (`surat_jalan_id IS NULL`) dan muncul di Daftar Belanja Kering (§Module 5).
- **Revisi Tier 2** yang belum disetujui Ops tidak pernah ikut dokumen.
- **Idempotency & Double-Click Guard:** `DB::transaction()` + `lockForUpdate()` pada baris `order_items`, retry nomor dokumen pada duplicate key, dan tombol frontend *disabled* saat proses.
- **Akses Dapur:** Dapur mengunduh dokumen per dokumen dari detail Order; akses dibatasi `surat_jalans.dapur_id`.
- **Template Dokumen *(2026-11-09)*:** Admin mengunggah format Word (`.docx`) per jenis dokumen di master Template Dokumen. Selama ada template aktif untuk jenis itu, Ops wajib memilih satu template saat generate; satu proses generate memakai satu template. Armada dan Driver dipilih dari master bila template memuat penandanya, lalu nilainya disalin ke dokumen. File template tidak bisa ditimpa; template yang sudah dipakai hanya bisa dinonaktifkan. Selama belum ada template aktif, sistem memakai format PDF bawaan.

### MODULE 4: STT QC PASS/REJECT & RECONCILED SPLIT BILLING
- **Dapur STT Input:** Dapur menginput data penerimaan barang berdasarkan Surat Tanda Terima (STT) fisik (`qty_received` & `qty_rejected`) untuk setiap `order_item` yang sudah memiliki Surat Jalan (`surat_jalan_id IS NOT NULL`). Input ini wajib ada sebelum Order tersebut bisa ditagih — lihat status implementasi di §5.
- **Foto Kedatangan & Crosscheck H+1 *(2026-11-07)*:** Dapur mengunggah foto per dokumen pengiriman (`delivery_document_photos`, JPG/PNG/WebP, maks. 5 MB, maks. 10 per dokumen; privat). Tim koperasi (permission `delivery-verifications.manage`) mencocokkan qty pesan, STT, nota (khusus TT basah), dan foto, lalu menandai dokumen `VERIFIED` (butuh STT lengkap) atau `DISCREPANCY` (catatan wajib). Invoice ditolak selama ada dokumen (tanpa `order_id`) di Order itu yang belum `VERIFIED`; dokumen lama ber-`order_id` dikecualikan. STT & foto terkunci setelah `VERIFIED`. Membuka kembali dari `VERIFIED` ditolak bila invoice atau tagihan supplier sudah ISSUED/OVERDUE/PAID. Status `DISCREPANCY` mem-`VOID` invoice dan tagihan supplier DRAFT terkait. Persetujuan invoice memeriksa ulang syarat ini. Foto tidak wajib untuk verifikasi. Cek bersama akuntan dapur tidak dicatat di sistem.
- **Status `COMPLETED`:** Order selesai saat semua invoice-nya terbit **dan** tidak ada baris yang belum masuk dokumen pengiriman.
- **Reconciled Split Billing Execution:**
  - Tim Finance menekan *Generate Split Invoices*.
  - Sistem merekonsiliasi seluruh Surat Jalan (Utama + Susulan) di bawah `Order ID` yang sama, mengelompokkannya per `supplier_id`, dan menghitung tagihan **hanya dari item yang lolos QC**:
    $$\text{Total Invoice per Supplier} = \sum \left(\text{qty\_received} \times \text{final\_selling\_price}\right)$$
- **PDF Rendering:** Menghasilkan PDF Invoice resmi per Supplier ID dengan format nomor **`INV/{YYYYMMDD}/{DAPUR_CODE}/{SUPP_CODE}`**, di mana `{SUPP_CODE}` adalah kolom `suppliers.code` (contoh: `SUP-BERAS`) — **bukan** `suppliers.id` numerik. PDF merangkum seluruh rincian pengiriman untuk keperluan audit LPJ.
- **Template Invoice *(2026-11-09)*:** Selama ada template Invoice aktif, Finance wajib memilih template saat generate, dan memilih Rekening Bank bila template memuat penanda bank. Nilai rekening disalin ke invoice. PDF dibuat setelah invoice disetujui. Invoice tambahan untuk barang susulan pada invoice lunas wajib memakai template yang memuat `${total_barang}` dan `${sudah_dibayar}`.

### MODULE 5: GUDANG & PURCHASING *(baru, v5.1.0)*
Mengelola buffer stok untuk barang `DRY_GOODS` — barang yang tidak perlu/tidak boleh dibeli dadakan setiap hari (beda dengan `WET_GOODS` yang tetap langsung dari Supplier, §Module 2).

- **Revisi 2026-11-05 — Belanja langsung dengan Nota (menggantikan draft PO otomatis):** bahan kering dibeli langsung oleh staf koperasi (mis. hari Sabtu). Sistem **tidak lagi membuat draft Purchase Order otomatis**; menu PO Purchasing & "Terima Barang" Gudang disembunyikan (data/halaman PO lama tetap ada untuk riwayat).
  - **Daftar Belanja Kering:** kebutuhan DRY_GOODS semua dapur yang belum terkirim (order s.d. tanggal kirim tertentu, bukan pending Tier 2) dikurangi stok Gudang = jumlah yang perlu dibeli; bisa dicetak.
  - **Nota Pembelian kering** (`purchase_notes` + `purchase_note_items`, category `DRY_GOODS`): tiap baris membuat `stock_lots` baru (harga nota, `purchase_note_item_id`) + `stock_movements` `PURCHASE_IN`; harga master barang diperbarui ke harga nota. Koreksi nota hanya selama lot-nya belum terpakai.
  - **Nota Pembelian basah** (category `WET_GOODS`): satu per supplier pada satu Tanda Terima (§Module 3; unik `surat_jalan_id` + `supplier_id`). Harga nota menjadi `final_cost_price` baris pesanan itu (harga jual = margin), sehingga harga bisa berbeda per dapur; `daily_prices` & harga master diperbarui ke harga nota terakhir; selisih qty nota vs pesanan ditandai `has_discrepancy`. Nota (baru maupun koreksi) ditolak setelah invoice atau tagihan supplier terkait ISSUED/OVERDUE/PAID, dan mem-`VOID` yang masih DRAFT. Baris dengan qty 0 tidak mengubah harga harian/master, dan nota bertanggal lebih lama tidak memundurkan harga master. Input Harga Harian manual (§Module 2) tidak menimpa baris yang sudah berharga nota.
  - Permission: `purchase-notes.manage` (role Purchasing & Admin).
- **~~Pemicu Otomatis~~ (dihentikan 2026-11-05):** Saat Ops men-generate Surat Jalan (§Module 3) dan stok Gudang untuk suatu item DRY_GOODS tidak cukup, sistem membuat **draft Purchase Order** ke `default_supplier_id` item tersebut untuk seluruh kuantitas yang kurang pada baris itu (tidak ada pemenuhan sebagian pada satu baris `order_item` — baris itu ditahan penuh sampai stok cukup).
- **Review & Kirim PO:** Tim Purchasing meninjau draft PO (boleh menyesuaikan qty/harga), lalu mengirimkannya ke Supplier (`DRAFT` → `SENT`).
- **Penerimaan Barang:** Tim Gudang mencatat barang yang diterima fisik dari PO yang `SENT`/`PARTIALLY_RECEIVED` — setiap penerimaan membuat satu `stock_lots` baru (qty + harga beli aktual) dan memperbarui status PO (`PARTIALLY_RECEIVED` atau `RECEIVED` bila semua baris lunas).
- **Konsumsi FIFO:** Saat Surat Jalan diterbitkan, stok DRY_GOODS ditarik dari `stock_lots` tertua (`received_at` paling awal) dulu. Harga pokok (`final_cost_price`) pada `order_items` adalah biaya rata-rata tertimbang dari lot-lot yang terpakai; `final_selling_price` dihitung dari margin item seperti biasa.
- **Keterbatasan saat ini:** satu Gudang pusat untuk semua Dapur (bukan multi-lokasi); item DRY_GOODS tanpa `default_supplier_id` tidak bisa dibuatkan draft PO otomatis — perlu diisi dulu di master Barang; Stock Opname (rekonsiliasi stok fisik vs sistem) belum dibangun — lihat §5 Status Implementasi.

---

## 4. TECHNICAL BLUEPRINT & LARAVEL SERVICE ARCHITECTURE

### 4.1 Direktori Struktur Layanan (`app/`)

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── PricingController.php      # WET_GOODS saja
│   │   │   ├── RoleController.php
│   │   │   ├── UserController.php
│   │   │   ├── ItemController.php
│   │   │   ├── SatuanController.php
│   │   │   ├── SupplierController.php
│   │   │   └── DapurController.php
│   │   ├── Dapur/
│   │   │   └── OrderController.php
│   │   ├── Ops/
│   │   │   ├── DeliveryNoteController.php
│   │   │   └── RevisionController.php     # Tier 2 approve/reject
│   │   ├── Purchasing/
│   │   │   └── PurchaseOrderController.php
│   │   ├── Gudang/
│   │   │   ├── PurchaseOrderController.php  # terima barang
│   │   │   └── StockController.php          # lihat stok
│   │   └── Finance/
│   │       └── InvoiceController.php
│   └── Requests/
│       ├── StoreOrderRequest.php
│       └── GenerateDeliveryNoteRequest.php
├── Models/
│   ├── User.php
│   ├── Dapur.php
│   ├── Supplier.php
│   ├── Satuan.php
│   ├── Item.php
│   ├── Order.php
│   ├── OrderItem.php
│   ├── SuratJalan.php
│   ├── DailyPrice.php
│   ├── Invoice.php
│   ├── AuditLog.php
│   ├── Gudang.php
│   ├── PurchaseOrder.php
│   ├── PurchaseOrderItem.php
│   ├── StockLot.php
│   └── StockMovement.php
├── Services/
│   ├── PricingEngineService.php
│   ├── RevisionGateService.php
│   ├── DeliveryNoteService.php            # kini juga menggerbangi stok DRY_GOODS
│   ├── SplitInvoiceService.php
│   ├── StockService.php                   # saldo stok + konsumsi FIFO
│   └── PurchaseOrderService.php           # draft PO dari shortfall + kirim PO
└── Jobs/
    └── GenerateInvoicePdfJob.php
```

### 4.2 Core Service Classes Implementation

#### **A. Delivery Note Service (Primary & Add-on SJ Guard + Gating Stok DRY_GOODS)**
Menangani penerbitan Surat Jalan Utama dan Surat Jalan Susulan anti-duplikasi, sekaligus menggerbangi stok Gudang untuk item DRY_GOODS.

```php
namespace App\Services;

use App\Models\{Gudang, Order, OrderItem, SuratJalan, User};
use Illuminate\Support\Facades\DB;

class DeliveryNoteService
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly PurchaseOrderService $purchaseOrderService,
    ) {}

    /**
     * @return array{surat_jalan: ?SuratJalan, purchase_orders: \Illuminate\Support\Collection}
     */
    public function generateSuratJalan(int $orderId, int $userId): array
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $user = User::findOrFail($userId);
            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();
            $gudang = Gudang::query()->firstOrFail();

            $unassigned = OrderItem::where('order_id', $orderId)
                ->whereNull('surat_jalan_id')
                ->with('item')
                ->lockForUpdate()
                ->get();

            if ($unassigned->isEmpty()) {
                throw new \RuntimeException('Tidak ada item baru yang perlu dibuatkan Surat Jalan.');
            }

            // DRY_GOODS: gerbang stok. WET_GOODS: selalu lolos (tidak distok).
            [$toAssign, $shortfalls] = $this->partitionByStock($unassigned, $gudang);

            $purchaseOrders = $shortfalls->isNotEmpty()
                ? $this->purchaseOrderService->generateDraftsForShortfall($order, $gudang, $shortfalls, $user)
                : collect();

            if ($toAssign->isEmpty()) {
                return ['surat_jalan' => null, 'purchase_orders' => $purchaseOrders];
            }

            $existingSjCount = SuratJalan::where('order_id', $orderId)->count();
            $sjType = $existingSjCount === 0 ? 'PRIMARY' : 'ADDON';

            $suratJalan = SuratJalan::create([
                'sj_number' => $this->buildSjNumber($sjType, $order, $existingSjCount + 1),
                'order_id' => $orderId,
                'type' => $sjType,
                'sequence_number' => $existingSjCount + 1,
                'printed_by' => $userId,
            ]);

            // v5.1.0: pemicu resmi status LOCKED, dirujuk oleh Module 2.
            if ($sjType === 'PRIMARY' && $order->status === 'SUBMITTED') {
                $order->update(['status' => 'LOCKED']);
            }

            $this->assignAndConsumeStock($toAssign, $suratJalan, $gudang, $user);

            return ['surat_jalan' => $suratJalan, 'purchase_orders' => $purchaseOrders];
        });
    }
}
```

#### **B. Pricing & Automated Master Sync Service (WET_GOODS)**
```php
namespace App\Services;

use App\Models\{Item, DailyPrice, OrderItem};
use Illuminate\Support\Facades\DB;

class PricingEngineService
{
    public function processDailyPrices(array $pricesData, string $date, int $userId): void
    {
        DB::transaction(function () use ($pricesData, $date, $userId) {
            foreach ($pricesData as $data) {
                // $data['item_id'] harus WET_GOODS — divalidasi di controller.
                $item = Item::findOrFail($data['item_id']);
                $sellingPrice = $item->calculateSellingPrice($data['cost_price']);

                DailyPrice::updateOrCreate(
                    ['item_id' => $item->id, 'date' => $date],
                    ['cost_price' => $data['cost_price'], 'calculated_price' => $sellingPrice, 'created_by' => $userId],
                );

                OrderItem::whereHas('order', fn ($q) => $q->where('delivery_date', $date)->where('status', 'LOCKED'))
                    ->where('item_id', $item->id)
                    ->update(['final_cost_price' => $data['cost_price'], 'final_selling_price' => $sellingPrice]);

                $item->update(['base_cost_price' => $data['cost_price'], 'base_selling_price' => $sellingPrice]);
            }
        });
    }
}
```

#### **C. Reconciled Split Invoice Service**
Merekonsiliasi seluruh item dari semua Surat Jalan (Utama & Susulan) untuk pembuatan Invoice final. Nomor invoice memakai **kode** Supplier, bukan ID.

```php
namespace App\Services;

use App\Models\{Order, Invoice};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SplitInvoiceService
{
    public function generateInvoicesForOrder(Order $order): Collection
    {
        return DB::transaction(function () use ($order) {
            $orderItems = $order->orderItems()->whereNotNull('qty_received')->with('supplier')->get();
            $invoices = collect();

            foreach ($orderItems->groupBy('supplier_id') as $supplierId => $items) {
                $supplier = $items->first()->supplier;
                $totalAmount = $items->sum(fn ($item) => $item->qty_received * $item->final_selling_price);

                $invoice = Invoice::updateOrCreate(
                    ['order_id' => $order->id, 'supplier_id' => $supplierId],
                    [
                        'invoice_number' => sprintf('INV/%s/%s/%s', now()->format('Ymd'), $order->dapur->code, $supplier->code),
                        'dapur_id' => $order->dapur_id,
                        'total_amount' => $totalAmount,
                        'status' => 'ISSUED',
                    ],
                );

                $invoices->push($invoice);
            }

            $order->update(['status' => 'COMPLETED']);

            return $invoices;
        });
    }
}
```

---

## 5. STATUS IMPLEMENTASI *(baru, v5.1.0)*

Lampiran ini melacak kesenjangan antara PRD dan kode aktual per 2026-10-21, supaya dokumen ini tidak kembali usang tanpa disadari. Hapus baris begitu sudah dikerjakan.

| # | Item | Modul | Status |
|---|---|---|---|
| 1 | Form input `qty_received`/`qty_rejected` oleh Dapur (STT fisik) | Module 4 | 🔴 Belum dibangun — **blocking**, tanpa ini Invoice tidak pernah bisa terbit |
| 2 | Transisi `Order.status` ke `LOCKED` saat SJ PRIMARY terbit | Module 3 | 🔴 Belum dibangun di kode (sudah dispesifikasikan di §4.2.A di atas) |
| 3 | Nomor Invoice pakai `supplier.code`, bukan `supplier.id` | Module 4 | 🔴 Kode aktual masih pakai ID — perlu diperbaiki (sudah dispesifikasikan di §4.2.C di atas) |
| 4 | Permission `delivery-notes.manage` untuk role `finance` dicabut dari rencana (role `ops` saja) | Module 3 | ✅ Sudah sesuai kode aktual — PRD yang disesuaikan ke kode |
| 5 | Notifikasi aktif (bukan cuma halaman list) untuk Tier 2 pending approval | Module 1 | 🟡 Belum ada — Ops harus cek manual ke halaman Approval Revisi |
| 6 | Stock Opname (rekonsiliasi stok fisik vs sistem) | Module 5 | 🟡 Belum dibangun — ledger `stock_lots`/`stock_movements` sudah siap sebagai dasarnya |
| 7 | Multi-lokasi Gudang | Module 5 | ⚪ Di luar scope saat ini (keputusan Boss: satu gudang pusat) |
