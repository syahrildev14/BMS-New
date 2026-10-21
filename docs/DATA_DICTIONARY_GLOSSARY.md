# Data Dictionary & Glossary — Sistem BMS

> Sumber kebenaran skema database & istilah bisnis. Bagian A diambil dari pembacaan langsung 58 migration sistem lama (`BLUEPRINT_SISTEM.md` §5); catatan "**[MIGRASI FK]**" menandai tabel yang akan mendapat kolom FK baru sesuai F-48 di `FINDING_REGISTRY.md`, dengan nama kolom pasti ditentukan saat penulisan migration (belum final, jangan dianggap sudah locked).

---

## Bagian A — Skema Database Kanonikal

| Tabel | Kolom Penting | Relasi FK Keluar | Catatan |
|---|---|---|---|
| `users` | id, name, nama, email, password, role (admin/super_admin), role_label, remember_token — tanpa timestamps | — | — |
| `user_api_tokens` | user_id, token_hash, expires_at, revoked_at | → users | **Kandidat dihapus** jika F-57 dikonfirmasi ke session-based (F-43) |
| `permissions` | code (unik), name, group_name, description | — | Sumber: `PermissionCatalog.php`, lihat Bagian C |
| `user_permissions` | user_id, permission_id (unique pair) | → users, permissions | — |
| `activity_logs` | user_id, user_name, module, action, method, request_path, description, metadata (json), ip_address | → users (nullable) | Pengisian pindah ke Observer generik (F-46) |
| `wilayah` | nama, alamat | — | — |
| `supplier` | nama, alamat, no_telp, kategori | — | — |
| `mitra` | nama_yayasan, alamat, nama_pic, no_pic | — | — |
| `sppg` | kode_sppg, nama_sppg, alamat, nama_yayasan, nama_penanggungjawab, no_penanggungjawab | — | Kunci matching lama: `nama_sppg` (string) — lihat **[MIGRASI FK]** di `order_penawaran` |
| `produk` | sku, nama, kategori, satuan | — | Kunci matching stok lama: `nama` (string, `LOWER(TRIM())`) |
| `gudang` | nama_gudang, alamat, nama_pic, no_pic | — | — |
| `armada` | nama_unit, no_pol, jenis_kendaraan (Roda 2/Roda 4) | — | — |
| `karyawan` | nama, alamat, no_hp, jabatan, tanggal_masuk, status (aktif/nonaktif) | — | — |
| `bank_rekening` | nama_bank, no_rek, atas_nama, cabang | — | — |
| `kategori` | kode, nama_satuan | — | — |
| `perusahaan` | nama_perusahaan, alamat, nama_pic, logo_path, tema_invoice (default `theme_01`) — tanpa timestamps | — | Logo diserve via endpoint tanpa auth + base64 inline (F-26) |
| `template_surat` | nama_template, jenis_dokumen (invoice/surat_jalan/tanda_terima), is_default, file_name, file_path | — | Constraint "1 default per jenis" dijaga aplikatif, bukan DB |
| `order_penawaran` | tanggal_pesan, tanggal_dikirim, nama_pembeli, keterangan | — | **[MIGRASI FK]** `nama_pembeli` → rencana tambah `sppg_id` |
| `order_penawaran_items` | order_penawaran_id, nama_barang, qty, satuan, harga_satuan, keterangan, produk_id, kategori_id, supplier_id | → order_penawaran, produk, kategori, supplier | Sudah ada `produk_id` — konsistensi dgn `nama_barang` perlu dicek |
| `daftar_pembelanjaan` | tanggal_pesan | — | — |
| `daftar_pembelanjaan_items` | daftar_pembelanjaan_id, nama_barang, qty, satuan, stok, kebutuhan, nama_supplier, supplier_id, produk_id, kategori_id | → daftar_pembelanjaan, supplier, produk, kategori | — |
| `warehouse_inbounds` | gudang_id, nama_barang, kategori (basah/kering), tanggal_masuk, qty, satuan, harga_satuan, total_harga, nama_supplier | → gudang | **[MIGRASI FK]** `nama_barang` → rencana tambah `produk_id` wajib (bukan nullable) |
| `warehouse_stok_kering` / `warehouse_stok_basah` | warehouse_inbound_id, gudang_id, nama_barang, qty, satuan_terkecil, harga_beli | → warehouse_inbounds, gudang | Relasi 1:1 per inbound (F-19) — sinkron dgn `SyncWarehouseStockAction` baru (Rebuild §9) |
| `warehouse_retur` | gudang_id, jenis_stok, nama_barang, qty_retur, satuan_terkecil, harga_beli, alasan | → gudang | — |
| `penjualan` | order_penawaran_id (unik, nullable), kode_penjualan (unik), tanggal, status (default draft), total_harga | → order_penawaran | — |
| `penjualan_items` | penjualan_id, order_penawaran_item_id, gudang_id (nullable), produk_id, perusahaan_id, nama_barang, qty, satuan, harga_satuan, total_harga | → penjualan, order_penawaran_items, gudang, produk, perusahaan | — |
| `surat_jalan` | nomor_surat_jalan (unik), no_po, tanggal, sppg_id, armada_id, driver_id (→karyawan), perusahaan_id, status | → sppg, armada, karyawan, perusahaan | Sudah FK murni ke sppg — bukan kandidat migrasi F-48 |
| `surat_jalan_items` | surat_jalan_id, penjualan_item_id, nama_barang, qty, satuan, keterangan | → surat_jalan, penjualan_items | — |
| `tanda_terima` | nomor_tanda_terima (unik), nomor_surat_jalan, no_po, tanggal, sppg_id, armada_id, akuntan_id, driver_id, perusahaan_id, status | → sppg, armada, karyawan (x2), perusahaan | — |
| `tanda_terima_items` | tanda_terima_id, penjualan_item_id, nama_barang, qty, satuan, keterangan | → tanda_terima, penjualan_items | — |
| `invoice_penjualan` | nomor_invoice (unik), penjualan_id, sppg_id, accounting_id, bank_rekening_id, perusahaan_id, tanggal_invoice, total_tagihan, status_pembayaran (default "belum lunas"), stock_deducted_at | → penjualan, sppg, karyawan, bank_rekening, perusahaan | Pencarian penjualan terkait masih via `nama_pembeli = nama_sppg` (F-21) — ditutup oleh migrasi FK `order_penawaran.sppg_id` |
| `invoice_penjualan_stock_movements` | invoice_penjualan_id, stock_table, stock_id, gudang_id, nama_barang, satuan_terkecil, harga_beli, qty | → invoice_penjualan | Audit trail deduksi stok FIFO (F-23) |
| `pemasukan` | tanggal, jenis, jumlah, keterangan | — | — |
| `pengeluaran` | nama_operasional, tanggal_keluar, satuan, harga_satuan | — | — |

**Catatan konsistensi**: kolom bertanda **[MIGRASI FK]** adalah kandidat migrasi relasi string→FK (F-48/F-54). Nama kolom baru final ditentukan saat menulis migration sesungguhnya di Claude Code, bukan sudah dikunci di dokumen ini.

---

## Bagian B — Glossary Istilah Bisnis

| Istilah | Arti |
|---|---|
| **SPPG** | Satuan Pelayanan Pemenuhan Gizi — dapur/unit penerima yang dilayani program gizi/MBG; entitas pembeli utama dalam sistem |
| **MBG** | Program Makan Bergizi Gratis — konteks program nasional yang dilayani sistem |
| **Order Penawaran** | Dokumen penawaran awal (semacam RFQ) berisi daftar barang + harga per item yang ditawarkan ke SPPG, sebelum jadi Penjualan resmi |
| **Penjualan** | Transaksi jual resmi, item bisa diimpor dari Order Penawaran; status `draft` sampai `selesai` |
| **Daftar Pembelanjaan** | Rekap kebutuhan pembelian barang, dikelompokkan per supplier (`daftar_pembelanjaan_supplier` = agregasi read-only) |
| **Surat Jalan** | Dokumen pengiriman fisik barang, direferensikan ke item Penjualan |
| **Tanda Terima** | Dokumen konfirmasi barang telah diterima SPPG, prasyarat sebelum Invoice bisa dibuat |
| **Invoice Penjualan** | Tagihan resmi ke SPPG, dibuat dari kombinasi SPPG+tanggal kirim; memicu pengurangan stok (F-23) |
| **Warehouse Inbound** | Pencatatan barang masuk gudang, sumber utama stok kering/basah |
| **Stok Kering / Stok Basah** | Dua kategori penyimpanan barang gudang (`kategori` di warehouse_inbounds), masing-masing tabel stok terpisah |
| **Retur** | Pencatatan barang gudang yang dikembalikan/rusak |
| **Mitra** | Yayasan/organisasi mitra yang terkait SPPG |
| **Wilayah** | Data referensi area/lokasi |
| **Template Surat** | File DOCX mentah sebagai template untuk generate dokumen (invoice/surat jalan/tanda terima) via placeholder |
| **Pemasukan / Pengeluaran** | Modul keuangan dasar — pencatatan arus kas masuk/keluar |
| **Activity Log** | Audit trail otomatis semua aksi tulis (create/update/delete) di seluruh modul |

---

## Bagian C — Kerangka Katalog Permission

Format kode: **`<modul>.<resource>.<aksi>`**, dengan `<aksi>` ∈ {`view`, `create`, `update`, `delete`}. Total **89 kode** terdefinisi statis di `PermissionCatalog::all()` (sistem lama).

**Catatan penting**: audit `BLUEPRINT_SISTEM.md` mencatat *jumlah* dan *format* kode, tapi **tidak mengutip isi lengkap 89 kode tersebut secara verbatim**. Tabel di bawah adalah kerangka modul/resource yang diketahui dari struktur controller (§3.2) — **bukan daftar 89 kode final**. Untuk sumber kebenaran lengkap, ekstrak langsung dari `app/Support/PermissionCatalog.php` di repo lama sebagai prompt Claude Code berikutnya.

| Modul | Resource (kandidat, dari §3.2) | Perkiraan aksi |
|---|---|---|
| Master Data | armada, bank-rekening, gudang, karyawan, kategori, mitra, perusahaan, produk, sppg, supplier, template-surat, wilayah (11) | view/create/update/delete |
| Transaksi Pembelian | order-penawaran, daftar-pembelanjaan | view/create/update/delete |
| Transaksi Penjualan | penjualan, surat-jalan, tanda-terima, invoice-penjualan | view/create/update/delete |
| Warehouse System | warehouse-inbound, stok-kering, stok-basah, retur | view/create/update/delete |
| Keuangan & Akuntansi | pemasukan, pengeluaran | view/create/update/delete |
| Laporan & Analisa | laba-rugi, stok-barang, penjualan-per-sppg | view (read-only) |
| Dashboard | — | umumnya tidak digate per-permission granular (§3.2: 5 controller single-action) |
| User Management | user, permission | view/create/update/delete |
| Activity Log | activity-log | view |

**Rekomendasi tindak lanjut**: jalankan ekstraksi langsung dari source `PermissionCatalog.php` via Claude Code, tempel hasilnya sebagai tabel lengkap di bagian ini menggantikan kerangka di atas — supaya dokumen ini benar-benar jadi sumber kebenaran 1:1, bukan rekonstruksi.

---

*Dokumen ini dirujuk oleh `PRD.md` dan `FINDING_REGISTRY.md`. Bagian A & B adalah fakta dari audit; Bagian C adalah kerangka yang perlu dilengkapi dari source code asli.*
