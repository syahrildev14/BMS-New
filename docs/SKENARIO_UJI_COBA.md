# Skenario Uji Coba BMS (UAT Staging)

Dokumen ini menguji alur koperasi dari order sampai pelunasan, termasuk barang susulan, invoice tambahan, dan penggunaan bersamaan di MySQL.

**Cara memakai:** kerjakan grup secara berurutan, karena angka stok saling menyambung. Centang kolom **OK** bila hasilnya sama persis. Bila berbeda, catat di [Log Temuan](#log-temuan).

## 0. Persiapan

Jalankan di **staging dengan MySQL 8**, jangan di production. Semua perbaikan harus sudah di-commit dan ter-deploy ke staging.

| OK | Langkah | Hasil diharapkan |
|---|---|---|
| ☐ | Backup database staging bila berisi data | File backup tersimpan |
| ☐ | Deploy lalu jalankan `SHOW INDEX FROM invoices;` dan `SHOW INDEX FROM supplier_bills;` | Ada `..._order_id_supplier_id_index` (non-unique) dan tidak ada lagi `..._order_id_supplier_id_unique` |
| ☐ | Cek `APP_DEBUG=false` di staging | Tampilan error sama seperti production |
| ☐ | Cek container `queue` dan `scheduler` berjalan | Status Up |

### Akun uji

| Akun | Role | Keterangan |
|---|---|---|
| `dapur-a` | dapur | Terhubung ke Dapur A |
| `dapur-b` | dapur | Terhubung ke Dapur B |
| `ops` | ops | Pengiriman, Crosscheck H+1, Approval Revisi |
| `purchasing` | purchasing | Harga Harian, Daftar Belanja, Nota |
| `gudang` | gudang | Stok Barang |
| `finance` | finance | **Maker:** generate invoice dan utang supplier |
| `admin` | admin | **Checker:** setujui, tolak, tandai lunas |

Role `finance` bawaan tidak punya izin menyetujui, jadi peran Checker di dokumen ini dijalankan oleh `admin`.

### Data master

| Jenis | Data |
|---|---|
| Dapur | Dapur A (kode `DPR-A`), Dapur B (kode `DPR-B`) |
| Supplier | `SUP-BASAH`, `SUP-KERING` |
| Barang basah (`WET_GOODS`, supplier `SUP-BASAH`) | Cabai, Bayam, Tomat. Satuan kg, margin 10% |
| Barang kering (`DRY_GOODS`, supplier `SUP-KERING`) | Beras, Gula. Satuan kg, margin 10% |

### Tanggal

**H** adalah besok dari hari uji. H+1, H+2, dan seterusnya dihitung dari H. Buat order **sebelum pukul 17.00 WIB** agar masuk Tier 1, kecuali di Grup F.

---

## A. Jalur utama sampai invoice terbit

Order Dapur A, tanggal kirim H: Cabai 10, Bayam 5, Beras 20, Gula 10.

| No | OK | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|
| A1 | ☐ | dapur-a | Order Saya → buat order tanggal H dengan 4 barang di atas | Pesan sukses `tier: TIER_1`. Harga pokok, margin, dan supplier tidak terlihat |
| A2 | ☐ | purchasing | Buka Daftar Belanja Kering sampai tanggal H | Beras perlu beli 20, Gula perlu beli 10 |
| A3 | ☐ | purchasing | Nota Pembelian kering `SUP-KERING`: Beras 100 @ 10.000, Gula 5 @ 15.000 | Nota tersimpan |
| A4 | ☐ | gudang | Stok Barang | Beras 100, Gula 5 |
| A5 | ☐ | purchasing | Buka lagi Daftar Belanja Kering | Beras perlu beli 0, Gula perlu beli 5 |
| A6 | ☐ | ops | Pengiriman (SJ & TT): Dapur A, Surat Jalan, tanggal H → generate | Satu SJ `PRIMARY` untuk `SUP-KERING` berisi Beras. Gula ditahan seluruhnya, tidak terkirim sebagian |
| A7 | ☐ | gudang | Stok Barang | Beras 80, Gula tetap 5 |
| A8 | ☐ | ops | Pengiriman: Dapur A, Tanda Terima, tanggal H → generate | Satu TT `PRIMARY` untuk `SUP-BASAH` berisi Cabai dan Bayam. Barang basah tidak ada di SJ |
| A9 | ☐ | dapur-a | Detail order | Status `LOCKED`. PDF SJ dan TT bisa diunduh |
| A10 | ☐ | purchasing | Nota basah dari TT: Cabai 10 @ 30.000, Bayam 5 @ 8.000 | Nota tersimpan, tanpa tanda selisih |
| A11 | ☐ | dapur-a | Isi STT: Cabai diterima 10 ditolak 1, Bayam 5/0, Beras 20/0. Unggah foto | Tersimpan. PDF Tanda Terima terisi tersedia |
| A12 | ☐ | ops | Crosscheck H+1: verifikasi SJ dan TT | Keduanya `VERIFIED` |
| A13 | ☐ | finance | Invoice → generate untuk order ini | 2 DRAFT: `SUP-BASAH` **341.000**, `SUP-KERING` **220.000** |
| A14 | ☐ | finance | Utang Supplier → generate | 2 DRAFT: `SUP-BASAH` **310.000**, `SUP-KERING` **200.000** |
| A15 | ☐ | admin | Setujui 2 invoice dan 2 utang | Semua `ISSUED`, jatuh tempo +14 hari |
| A16 | ☐ | finance | Unduh PDF invoice `SUP-BASAH` | Subtotal Cabai 297.000 (9 × 33.000), Bayam 44.000, total 341.000 |
| A17 | ☐ | dapur-a | Detail order | Masih `LOCKED`, karena Gula belum terkirim |

## B. Barang susulan pada invoice yang belum lunas

Lanjutan Grup A. Invoice lama harus direvisi, bukan dibuat baru.

| No | OK | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|
| B1 | ☐ | purchasing | Nota kering `SUP-KERING`: Gula 5 @ 16.000 | Stok Gula menjadi 10 |
| B2 | ☐ | ops | Pengiriman: Dapur A, Surat Jalan, tanggal H → generate | Dokumen `ADDON` berisi Gula 10. Stok Gula 0 |
| B3 | ☐ | dapur-a | Isi STT Gula 10/0 | Tersimpan |
| B4 | ☐ | finance | Generate invoice sebelum Crosscheck | Ditolak: `Dokumen belum lolos Crosscheck H+1` |
| B5 | ☐ | ops | Verifikasi dokumen ADDON | `VERIFIED` |
| B6 | ☐ | finance | Generate invoice | Tidak ada baris invoice baru. `SUP-KERING` nomor tetap, status `DRAFT`, total **390.500**. `SUP-BASAH` tetap `ISSUED` |
| B7 | ☐ | finance | Coba unduh PDF invoice `SUP-KERING` | Tidak tersedia sampai disetujui ulang |
| B8 | ☐ | finance | Generate Utang Supplier | `SUP-KERING` nomor tetap, `DRAFT`, total **355.000** |
| B9 | ☐ | admin | Setujui invoice dan utang yang direvisi | `ISSUED`, PDF baru berisi Beras dan Gula |
| B10 | ☐ | dapur-a | Detail order | `COMPLETED` |

## C. Barang susulan pada invoice yang sudah lunas

Order Dapur B, tanggal kirim H+1: Beras 10, Gula 3. Stok awal: Beras 80, Gula 0.

| No | OK | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|
| C1 | ☐ | dapur-b | Buat order di atas | Sukses |
| C2 | ☐ | ops | Generate Surat Jalan Dapur B tanggal H+1 | `PRIMARY` berisi Beras. Gula ditahan |
| C3 | ☐ | dapur-b, ops | STT Beras 10/0, lalu verifikasi | `VERIFIED` |
| C4 | ☐ | finance, admin | Generate dan setujui invoice serta utang | Invoice 110.000, utang 100.000, keduanya `ISSUED` |
| C5 | ☐ | admin | Tandai invoice dan utang lunas | `PAID`, tanggal dan metode tercatat. Order tetap `LOCKED` |
| C6 | ☐ | purchasing | Nota kering: Gula 3 @ 20.000 | Stok Gula 3 |
| C7 | ☐ | ops | Generate Surat Jalan Dapur B tanggal H+1 | Dokumen `ADDON` berisi Gula |
| C8 | ☐ | dapur-b, ops | STT Gula 3/0, lalu verifikasi | `VERIFIED` |
| C9 | ☐ | finance | Generate invoice | Muncul invoice **baru** bernomor sendiri, `DRAFT`, **66.000**. Invoice lama tetap `PAID` 110.000 |
| C10 | ☐ | finance | Generate invoice sekali lagi | Tidak bertambah, memakai draft yang sama |
| C11 | ☐ | finance | Generate Utang Supplier | Utang **baru** `DRAFT` **60.000**. Utang lama tetap `PAID` |
| C12 | ☐ | admin | Setujui invoice dan utang tambahan | `ISSUED`. Order `COMPLETED` |
| C13 | ☐ | finance | Unduh PDF invoice tambahan | Beras 110.000, Gula 66.000, baris `Sudah Dibayar (nomor invoice lama)` -110.000, total 66.000 |
| C14 | ☐ | admin | Tandai invoice tambahan lunas | `PAID`. Invoice lama tidak berubah |

## D. Penolakan alur

Order Dapur A, tanggal kirim H+2: Cabai 10, Bayam 5. Generate Tanda Terima-nya lebih dulu.

Pada langkah bertanda ⚠️, penolakan sudah benar tetapi pengguna saat ini mungkin hanya melihat halaman error umum. Masalah tampilan ini sudah diketahui dan perbaikannya ditunda. Yang dinilai adalah datanya tidak berubah.

| No | OK | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|
| D1 | ☐ | finance | Generate invoice sebelum STT diisi ⚠️ | Ditolak, tidak ada invoice |
| D2 | ☐ | ops | Verifikasi sebelum STT diisi | Ditolak: barang belum diisi STT |
| D3 | ☐ | dapur-a, finance | Isi STT Cabai 10/0 dan Bayam 5/0, lalu generate invoice | Ditolak: `Dokumen belum lolos Crosscheck H+1` |
| D4 | ☐ | ops, finance | Verifikasi, lalu generate invoice | Ditolak: barang belum punya harga, Cabai dan Bayam tercantum |
| D5 | ☐ | dapur-a | Ubah STT setelah `VERIFIED` | Ditolak: dokumen sudah terverifikasi |
| D6 | ☐ | purchasing | Nota basah: Cabai 10 @ 30.000, Bayam qty 0 tanpa harga | Tersimpan dengan tanda selisih |
| D7 | ☐ | finance | Generate invoice | Masih ditolak, kali ini hanya Bayam. Tidak ditagih Rp0 |
| D8 | ☐ | purchasing | Harga Harian: Bayam tanggal H+2, harga beli 8.000 | Harga jual Bayam 8.800, harga master Bayam ikut berubah |
| D9 | ☐ | finance | Generate invoice | `DRAFT` **374.000** |
| D10 | ☐ | purchasing | Harga Harian: Tomat tanggal H+2, harga beli 5.000 | Tersimpan. Draft invoice tetap `DRAFT` |
| D11 | ☐ | dapur-a, ops | Tambah Tomat 2 ke order, generate TT, isi STT 2/0, verifikasi | TT `ADDON` berisi Tomat, `VERIFIED` |
| D12 | ☐ | admin | Setujui draft lama | Ditolak: `Total draft ini sudah tidak sesuai`. Tetap `DRAFT` |
| D13 | ☐ | finance | Generate invoice | Nomor sama, total **385.000** |
| D14 | ☐ | ops | Tandai TT `ADDON` sebagai `DISCREPANCY` dengan catatan | Draft invoice menjadi `VOID` |
| D15 | ☐ | ops, finance | Verifikasi lagi, lalu generate invoice | Nomor sama, `DRAFT`, 385.000 |
| D16 | ☐ | admin | Tolak invoice | `VOID` |
| D17 | ☐ | finance, admin | Generate lagi lalu setujui | `ISSUED`, order `COMPLETED` |
| D18 | ☐ | ops | Tandai dokumen `VERIFIED` di order ini sebagai `DISCREPANCY` | Ditolak: invoice sudah terbit, status dokumen tidak berubah |

## E. Harga, nota, dan invoice jatuh tempo

Order Dapur A, tanggal kirim H+3: Cabai 5, Gula 2. Stok Gula harus 0 agar order tetap `LOCKED`. Jalankan C14 lebih dulu, lalu pastikan stok Gula 0 di Stok Barang.

| No | OK | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|
| E1 | ☐ | purchasing | Harga Harian: Cabai tanggal H+3, harga beli 30.000 | Tersimpan |
| E2 | ☐ | ops | Generate Surat Jalan Dapur A tanggal H+3 | Gula ditahan, tidak ada dokumen |
| E3 | ☐ | ops | Generate Tanda Terima tanggal H+3 | TT `PRIMARY` berisi Cabai dengan harga dari Harga Harian |
| E4 | ☐ | dapur-a, ops | STT Cabai 5/0, verifikasi | `VERIFIED` |
| E5 | ☐ | finance, admin | Generate lalu setujui invoice | `ISSUED` **165.000**, order `LOCKED` |
| E6 | ☐ | purchasing | Harga Harian: Cabai tanggal H+3, harga beli 40.000 | Tersimpan. Harga baris dan invoice **tidak berubah**. Harga master Cabai menjadi 40.000 |
| E7 | ☐ | purchasing | Buat nota basah untuk TT `PRIMARY` | Ditolak: invoice sudah terbit |
| E8 | ☐ | staging | Buat invoice lewat tempo: `UPDATE invoices SET due_date = CURDATE() - INTERVAL 1 DAY WHERE invoice_number = '<nomor>';` lalu `php artisan finance:mark-overdue` | Status `OVERDUE` |
| E9 | ☐ | dapur-a, ops | Tambah Cabai 2, generate TT | TT `ADDON` berisi Cabai |
| E10 | ☐ | purchasing | Nota basah TT `ADDON`: Cabai 2 @ 35.000 | **Diterima**, harga jual 38.500 |
| E11 | ☐ | dapur-a, ops | STT Cabai 2/0, verifikasi | `VERIFIED` |
| E12 | ☐ | finance | Generate invoice | Nomor sama, `DRAFT`, **242.000** |
| E13 | ☐ | admin | Setujui | `ISSUED`, jatuh tempo baru. Order tetap `LOCKED` karena Gula ditahan |

## F. Order dan batas waktu

| No | OK | Waktu | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|---|
| F1 | ☐ | Sebelum 17.00 WIB, tanggal kirim bukan hari ini | dapur-a | Order baru | `TIER_1`, status `SUBMITTED` |
| F2 | ☐ | Sesudah 17.00 WIB, tanggal kirim bukan hari ini | dapur-a | Tambah barang | `TIER_2`, muncul di Approval Revisi |
| F3 | ☐ | Lanjutan F2 | ops | Generate dokumen sebelum disetujui | Barang pending tidak ikut |
| F4 | ☐ | Lanjutan F2 | ops | Setujui, lalu generate dokumen | Barang ikut dokumen |
| F5 | ☐ | Sesudah 17.00 WIB | dapur-a, ops | Tambah barang lain, lalu Ops tolak | Barang terhapus dari order |
| F6 | ☐ | Tanggal kirim hari ini | dapur-a | Order atau tambah barang | `TIER_3`, tercatat di Log Aktivitas |
| F7 | ☐ | Bebas | dapur-a | Order dengan tanggal kemarin | Ditolak validasi |
| F8 | ☐ | Bebas | dapur-a | Tambah order pada tanggal yang order-nya `COMPLETED` | Ditolak |
| F9 | ☐ | Bebas | dapur-a | Isi STT diterima lebih dari pesanan, atau ditolak lebih dari diterima | Ditolak validasi |
| F10 | ☐ | Bebas | dapur-a | Ubah qty atau hapus barang yang belum masuk dokumen | Berhasil |
| F11 | ☐ | Bebas | dapur-a | Ubah qty atau hapus barang yang sudah masuk dokumen ⚠️ | Ditolak, data tidak berubah |

## G. Hak akses

| No | OK | Pelaku | Langkah | Hasil diharapkan |
|---|---|---|---|---|
| G1 | ☐ | dapur-b | Buka URL detail order Dapur A | 403 |
| G2 | ☐ | dapur-b | Buka URL PDF dan foto dokumen Dapur A | 403 |
| G3 | ☐ | finance | Kirim aksi setujui invoice atau tandai lunas | 403 |
| G4 | ☐ | dapur-a | Buka menu Finance atau Purchasing lewat URL | 403 |
| G5 | ☐ | ops | Buka Invoice lewat URL | 403 |
| G6 | ☐ | gudang | Buka Stok Barang | Bisa dibuka |

## H. Penggunaan bersamaan (khusus MySQL)

Grup ini memeriksa row lock, yang belum bisa diuji di SQLite. Pakai dua browser berbeda dan tekan tombol sedekat mungkin, ulangi 3 kali tiap kasus. Waktu klik tidak bisa dijamin bersamaan, jadi nilai dari kebenaran hasil akhirnya. Setelah tiap kasus, jalankan [query pemeriksaan](#query-pemeriksaan).

| No | OK | Langkah | Hasil diharapkan |
|---|---|---|---|
| H1 | ☐ | Buat barang kering baru per ulangan (misalnya Tepung) dengan nota 10. Dapur A dan Dapur B masing-masing pesan 8, tanggal sama. Dua Ops generate Surat Jalan bersamaan | Satu mendapat dokumen, satu lagi barangnya ditahan. Tidak ada error 500. Stok akhir 2 |
| H2 | ☐ | Dua Ops generate dokumen yang sama untuk satu Dapur | Satu dokumen `PRIMARY`. Permintaan kedua menjawab tidak ada barang untuk dibuatkan dokumen |
| H3 | ☐ | Satu Dapur, dua tab, kirim order berbeda tanggal bersamaan | Keduanya sukses dengan nomor order berbeda |
| H4 | ☐ | Klik ganda kirim order pada tanggal yang sama | Satu Order per Dapur dan tanggal, tidak ada error 500 |
| H5 | ☐ | Dapur mengubah qty saat Ops generate dokumen | Salah satu menang. Qty baris sama dengan jumlah stok keluar |
| H6 | ☐ | Dapur menghapus barang saat Ops generate dokumen | Baris terhapus tanpa stok keluar, atau tetap ada di dokumen dan penghapusan ditolak |
| H7 | ☐ | Finance generate invoice saat Admin menyetujui draft yang sama | Total akhir sesuai barang, tidak ada invoice ganda |
| H8 | ☐ | Purchasing simpan Harga Harian saat Admin menyetujui invoice yang memuat barang itu | Bila invoice terbit dulu, harga baris tetap. Bila harga masuk dulu, persetujuan ditolak atau draft menjadi `VOID` |

### Query pemeriksaan

Semua query hanya membaca data. Hasil yang diharapkan: **kosong**.

```sql
-- Dokumen PRIMARY ganda
SELECT dapur_id, supplier_id, document_type, shipment_date, COUNT(*)
FROM surat_jalans
WHERE type = 'PRIMARY' AND supplier_id IS NOT NULL
GROUP BY dapur_id, supplier_id, document_type, shipment_date
HAVING COUNT(*) > 1;

-- Stok minus
SELECT id, item_id, qty_remaining FROM stock_lots WHERE qty_remaining < 0;

-- Qty baris tidak sama dengan stok keluar
SELECT oi.id, oi.qty_ordered, SUM(m.qty) AS keluar
FROM order_items oi
JOIN stock_movements m
  ON m.reference_type = 'App\\Models\\OrderItem'
 AND m.reference_id = oi.id
 AND m.type = 'ORDER_OUT'
GROUP BY oi.id, oi.qty_ordered
HAVING ROUND(SUM(m.qty), 2) <> ROUND(oi.qty_ordered, 2);

-- Stok keluar untuk baris yang sudah terhapus
SELECT m.id, m.reference_id
FROM stock_movements m
LEFT JOIN order_items oi ON oi.id = m.reference_id
WHERE m.reference_type = 'App\\Models\\OrderItem'
  AND m.type = 'ORDER_OUT'
  AND oi.id IS NULL;

-- Lebih dari satu invoice belum lunas per order dan supplier
SELECT order_id, supplier_id, COUNT(*)
FROM invoices
WHERE status <> 'PAID'
GROUP BY order_id, supplier_id
HAVING COUNT(*) > 1;

-- Lebih dari satu utang belum lunas per order dan supplier
SELECT order_id, supplier_id, COUNT(*)
FROM supplier_bills
WHERE status <> 'PAID'
GROUP BY order_id, supplier_id
HAVING COUNT(*) > 1;
```

## Log Temuan

| ID Skenario | Tanggal | Penguji | Hasil yang terjadi | Screenshot/log | Tingkat (Tinggi/Sedang/Rendah) | Status |
|---|---|---|---|---|---|---|
| | | | | | | |

## Kriteria Lulus

| OK | Kriteria |
|---|---|
| ☐ | Grup A, B, C, D, E, dan G lulus semua |
| ☐ | Grup H lulus semua dan query pemeriksaan kosong |
| ☐ | Grup F lulus, atau skenario yang tersisa disetujui penanggung jawab |
| ☐ | Tidak ada temuan tingkat Tinggi yang masih terbuka |
| ☐ | Keputusan atas tampilan error ⚠️ sudah dibuat: diperbaiki dulu atau diterima sementara |
