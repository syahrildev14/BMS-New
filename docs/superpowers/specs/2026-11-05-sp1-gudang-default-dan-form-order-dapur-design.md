# Sub-proyek 1 — Gudang Default & Penyederhanaan Form Order Dapur

**Tanggal:** 2026-11-05
**Status:** Disetujui (desain)
**Konteks:** Audit alur koperasi vs sistem. Sub-proyek 1 dari 6 (lihat urutan di bagian akhir).

## Masalah

1. Membuat order Dapur gagal di production: `RevisionGateService::autoMerge()` → `DeliveryNoteService::checkStockShortfalls()` memanggil `Gudang::query()->firstOrFail()`. Baris Gudang hanya dibuat oleh `GudangSeeder` (dipanggil `DatabaseSeeder`, yang diblokir di production). Production hanya menjalankan `RoleAndPermissionSeeder`, dan tidak ada UI master Gudang.
2. Dapur wajib mengisi **Estimasi Harga** dan **Supplier** per baris order, padahal di alur koperasi supplier dan harga ditentukan koperasi.
3. Baris order yang barangnya tidak punya `default_supplier_id` dibuang diam-diam di frontend (`create.tsx` filter `line.supplier_id`), sehingga user hanya melihat error generik "items wajib diisi".

## Desain

### a. Gudang default via migration
- Migration data baru: jika tabel `gudangs` kosong, insert satu baris `code = 'GDG-PUSAT'`, `name = 'Gudang Pusat'`, `address = null` (sama dengan `GudangSeeder`, sehingga seeder lokal tetap idempoten).
- Jika sudah ada baris apa pun, migration tidak melakukan apa-apa. `down()` tidak menghapus data.

### b. Form order Dapur: Barang + Qty saja
- **Backend**
  - `StoreOrderRequest` dan validasi `OrderController::addItems`: hapus `items.*.supplier_id` dan `items.*.estimated_price`. Tetap: `item_id` (harus `is_active = true`), `qty_ordered`.
  - Validasi tambahan: setiap `item_id` wajib punya `default_supplier_id`; jika tidak, error per baris: *"Barang {nama} belum punya supplier default, hubungi koperasi."*
  - Sebelum memanggil `RevisionGateService::submitRevision()`, controller memperkaya payload: `supplier_id = item.default_supplier_id`, `estimated_price = item.base_selling_price ?? 0`. Kontrak `submitRevision()` tidak berubah.
  - Untuk menghindari duplikasi, logika pengayaan ditaruh di satu tempat (method privat di `OrderController` atau method statis/helper kecil yang dipakai `store` dan `addItems`).
- **Frontend** (`resources/js/Pages/dapur/orders/create.tsx`, `show.tsx`)
  - Hapus input Supplier dan Estimasi Harga dari form *Buat Order* dan *Tambah Item*.
  - Payload hanya `item_id` + `qty_ordered`; filter baris hanya berdasarkan `item_id && qty_ordered`.
  - Error validasi per baris (`items.N.item_id`) ditampilkan di baris terkait.
  - Kolom "Harga Estimasi" di tabel detail order tetap tampil (read-only, informasi).
  - Prop `suppliers` yang tidak lagi dipakai dihapus dari controller `create`/`show` jika tidak dipakai bagian lain halaman.

### c. Error handling
- Barang tanpa supplier default → 422 validasi, tidak ada Order/OrderItem yang dibuat (validasi terjadi sebelum `findOrCreateOrderSafely`).
- Barang non-aktif → ditolak validasi.

### d. Pengujian (feature test)
- Dapur membuat order hanya dengan `item_id` + `qty_ordered` → sukses; `order_items.supplier_id` = default supplier barang, `estimated_price` = `base_selling_price`.
- Barang tanpa `default_supplier_id` → 422, tidak ada Order baru.
- `addItems` dengan payload baru → sukses dengan pengayaan yang sama.
- Migration Gudang: setelah migrate pada DB tanpa seeder, tepat ada 1 Gudang; order dapat dibuat.
- Test lama yang mengirim `supplier_id`/`estimated_price` disesuaikan (field ekstra boleh diabaikan, tapi nilai server yang dipakai).

## Di luar cakupan
Surat Jalan per supplier, PO manual, Nota, verifikasi penerimaan, koreksi invoice — Sub-proyek 2–6.

## Urutan sub-proyek
1. Gudang default + form Dapur (dokumen ini)
2. Surat Jalan khusus bahan kering, per supplier
3. PO manual + daftar belanja kering
4. Modul Nota
5. Penerimaan: foto, verifikasi H+1, cek akuntan
6. Koreksi invoice + kompensasi +Rp2.000
