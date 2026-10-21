# Laporan Update Terkini — Sistem BMS

> File laporan berdiri sendiri, TIDAK mengubah `FINDING_REGISTRY.md`, `docs/LAPORAN_AUDIT_RBAC.md`, atau dokumen lain yang sudah ada. Penomoran F-112 dst di bawah melanjutkan dari F-111 (dipakai Boss untuk konfirmasi "Penjualan belum dikerjakan") — usulan lanjutan, belum masuk registry resmi.

Merangkum 3 pekerjaan sejak laporan audit RBAC terakhir (`LAPORAN_AUDIT_RBAC.md`): (1) eksekusi penutupan `/register` publik, (2) audit + perbaikan backend Pembelian & Warehouse, (3) investigasi Fase A Penjualan (Surat Jalan/Tanda Terima/Invoice) — murni riset, tanpa kode.

---

## 1. Status Progres — Ringkasan Cepat

| # | Pekerjaan | Status |
|---|---|---|
| 1 | Matikan `/register` publik (eksekusi keputusan F-110) | ✅ **Selesai** |
| 2 | Audit fungsional Pembelian & Warehouse (7 resource) | ✅ **Selesai** — 1 bug diperbaiki, 3 temuan business logic dilaporkan (belum diputuskan) |
| 3 | Investigasi Fase A Penjualan (Surat Jalan/Tanda Terima/Invoice) | ✅ **Selesai (riset saja)** — 9 hal butuh keputusan Boss sebelum Fase B mulai |

Tidak ada yang di-commit di ketiga pekerjaan ini — semua masih di working tree.

---

## 2. Penutupan `/register` Publik (F-112)

**Status: RESOLVED**

Eksekusi keputusan F-110 (laporan sebelumnya). Route `GET`/`POST /register` dihapus dari `routes/auth.php`, link "Sign up" dihapus dari `login.tsx`. `RegisteredUserController` dihapus total (dicek dulu: tidak dipakai jalur lain — modul RBAC→User membuat user langsung dengan `role_id` wajib, tidak bergantung pada fallback role default yang ada di controller itu). Ikut dihapus sebagai konsekuensi: halaman `register.tsx` (jadi tidak terjangkau) dan `RegistrationTest.php` (test bawaan starter kit yang meng-assert route itu ada).

**Bukti verifikasi:**
```
GET  /register  → HTTP 404
POST /register  → HTTP 404
```
`php artisan route:list | grep register` → kosong. `npx tsc --noEmit` → 0 error baru.

---

## 3. Audit Pembelian & Warehouse (F-113–F-117)

**Metode:** baca silang migration↔model↔request↔resource↔controller per resource, ditambah test end-to-end nyata (Pest, HTTP asli, `RefreshDatabase`) menyusuri rantai List Order Penawaran → Daftar Pembelanjaan → Daftar Pembelanjaan Supplier → Inbound → Retur/Rusak. Test bersifat sementara, sudah dihapus setelah bukti dikumpulkan.

### F-113 — 1 bug mekanis ditemukan & diperbaiki
**Status: RESOLVED**

`DaftarPembelanjaanController::update()` tidak menegakkan aturan "1 List Order Penawaran = 1 Daftar Pembelanjaan" yang sudah ditegakkan `store()` — reassign `list_order_penawaran_id` lewat update bisa melanggar aturan itu diam-diam. Diperbaiki dengan re-check exists() yang sama seperti `store()`. Dibuktikan: reassign ke LOP yang sudah punya DP → sebelum fix **200** (lolos), sesudah fix **422**.

Pola bug F-101 (mismatch `->ignore($this->camelCase)` di Update Request hyphenated) **dicek eksplisit, tidak ditemukan** di modul ini — tidak ada Update Request yang punya rule `unique`+`ignore()` sama sekali. FK (`barang_id`/`supplier_id`/`gudang_id`) **dicek bersih**, semua FK murni, tidak ada sisa string-matching (F-32).

### F-114 — 🟡 `DaftarPembelanjaanSupplier::store()` tidak idempoten
**Status: OPEN — butuh keputusan Boss**

Endpoint generate header per-supplier dari `DaftarPembelanjaan.details`, tapi tidak mengecek apakah header untuk kombinasi (`daftar_pembelanjaan_id`, `supplier_id`) sudah pernah dibuat. Dipanggil 2x dengan input sama → data dobel (dibuktikan: 2 → 4 record). Terkait kolom `status` (`draft`/`proses`/`selesai`) di `daftar_pembelanjaans`/`daftar_pembelanjaan_suppliers` yang **didefinisikan tapi tidak pernah ditransisikan** oleh kode manapun — indikasi guard yang dimaksud belum diimplementasikan.

### F-115 — 🟡 Retur/Rusak bisa dibuat sebelum Inbound 'selesai'
**Status: OPEN — butuh keputusan Boss**

`ReturRusakController::store()` tidak mengecek `inbound.status`. Karena stok baru bertambah saat Inbound 'selesai', retur/rusak terhadap inbound yang masih 'draft' berarti mencatat retur atas barang yang belum pernah masuk stok — dibuktikan: `POST retur-rusak` terhadap inbound draft → berhasil (201).

### F-116 — 🟡 Tidak ada batas atas qty Retur/Rusak
**Status: OPEN — butuh keputusan Boss**

Tidak ada validasi qty retur terhadap `qty_diterima` atau stok yang tersedia. Dibuktikan: qty retur 999 pada stok 15 → stok jadi **-984**, tersimpan tanpa error.

### F-117 — Mekanisme sinkronisasi Inbound→Stok bersih
**Status: LOCKED (fakta positif)**

Hanya ADA SATU mekanisme (`InboundController::update()`), konsisten, benar-benar dipanggil dari route asli. **Tidak ada duplikasi ala F-19** (sistem lama punya 2 mekanisme tidak konsisten, salah satunya tak pernah dipanggil).

---

## 4. Investigasi Fase A Penjualan (F-118–F-123)

**Aturan kerja:** murni riset & usulan, **tidak ada migration/model/kode ditulis**, sesuai instruksi eksplisit. Menunggu Boss bilang "LANJUT" sebelum Fase B mulai.

### F-118 — 🔴 Koreksi: Tanda Terima TERNYATA sudah tabel persisten + CRUD penuh
**Status: LOCKED (fakta, koreksi asumsi awal)**

Premis awal ("belum dikerjakan sama sekali", F-111) **tidak sepenuhnya akurat** — pola yang sama seperti RBAC Fase C (F-98/F-105) terulang. `tanda_terimas` sudah jadi tabel nyata (`penjualan_id` unique 1:1, `tanggal`, `nama_penerima`, `jabatan` teks bebas, `catatan`), `TandaTerimaController` sudah CRUD lengkap, permission `penjualan.tanda-terima.{view,create,update,delete}` sudah lengkap di `RbacSeeder`. **Surat Jalan & Invoice** dikonfirmasi masih view turunan (F-74) — bagian ini yang premisnya akurat.

### F-119 — 🔴 Konflik: stok sudah dikurangi di Penjualan, bukan di Invoice
**Status: OPEN — blocking Fase C**

`PenjualanController::update()` **sudah** mengurangi `StokGudang` saat Penjualan diselesaikan — bertentangan langsung dengan F-23 ("pengurangan stok SAAT invoice dibuat, bukan saat penjualan"). Ini bukan cuma soal skema tabel baru — kalau F-23 mau dipertahankan literal, kode Penjualan yang **sudah jalan** harus diubah.
→ **Perlu keputusan:** pindahkan deduksi ke Invoice (ubah behavior existing), atau terima bahwa di bms-new deduksi terjadi lebih awal (di Penjualan) dan Invoice tidak perlu deduksi sendiri lagi?

### F-120 — 🔴 FIFO (F-23) tidak representable dengan model stok saat ini
**Status: OPEN — blocking Fase C**

`StokGudang` = satu baris agregat per barang+gudang dengan `harga_rata_rata` (weighted average), bukan per-lot/batch. Deduksi FIFO sungguhan butuh tabel lot/batch baru — scope tambahan di luar 6 tabel yang direncanakan.
→ **Perlu keputusan:** bangun tabel lot baru, atau turunkan requirement jadi "kurangi dari average stock" (bukan FIFO literal)?

### F-121 — 🔴 `perusahaan_id` (F-87) tidak bisa dipasang — modul Perusahaan sudah dihapus total
**Status: OPEN — kontradiksi antar-keputusan lama**

Tabel `perusahaans` sudah di-drop total (migration `drop_perusahaans_table`, "atas instruksi Boss"), tidak ada satupun referensi `perusahaan` tersisa di kode. F-87 (LOCKED) mengasumsikan modul itu masih ada.
→ **Perlu keputusan:** bangun ulang modul Perusahaan (scope baru), atau `perusahaan_id` di-drop total dari rencana Surat Jalan/Tanda Terima/Invoice?

### F-122 — 🟡 `driver_id`/`accounting_id` tidak ada tempat berlabuh
**Status: OPEN**

Skema lama mereferensikan `karyawan` (employee registry) — modul itu sudah dihapus total di bms-new (F-90/91), diganti `jabatans` yang cuma master data job-title, bukan registry pegawai.
→ **Perlu keputusan:** simpan sebagai teks bebas (pola `tanda_terimas.nama_penerima` yang sudah teks bebas), referensi ke `users`, atau drop dari skema?

### F-123 — 🔴 Arsitektur inti belum ditentukan: rantai 1:1 vs agregasi SPPG+tanggal
**Status: OPEN — paling blocking, menentukan bentuk skema `invoices`**

Kode existing (Tanda Terima unique per Penjualan, Surat Jalan/Invoice bind ke satu Penjualan) mengasumsikan rantai lurus 1 Penjualan : 1 Surat Jalan : 1 Tanda Terima : 1 Invoice. F-22 (dokumen lama) tersirat Invoice mengagregasi banyak Penjualan per SPPG+tanggal ("cegah duplikat invoice per SPPG+tanggal").
→ **Perlu keputusan Boss sebelum Fase B**, karena menentukan apakah `invoices.penjualan_id` FK tunggal atau butuh pivot many-to-many.

**Temuan minor (non-blocking):** permission slug — prompt investigasi minta usul `invoice-penjualan`, tapi kode existing sudah punya `penjualan.invoice.view` — direkomendasikan extend slug yang ada daripada bikin baru (hindari kode ganda/yatim). Penamaan tabel `invoices` vs `invoice_penjualans` — gaya, bukan teknis.

**Checklist Fase B/C/D sudah disusun** (dependency-aware: migration+permission → Action class+controller+route → frontend) — detail lengkap ada di respons investigasi sebelumnya, tidak diulang di sini karena semuanya menunggu F-119/F-120/F-121/F-123 diputuskan dulu (keputusan itu mengubah bentuk skema, jadi checklist detail belum final).

---

## 5. Konsolidasi — Semua Keputusan yang Ditunggu Boss

| # | Keputusan | Blocking? |
|---|---|---|
| F-114 | Guard idempotensi `DaftarPembelanjaanSupplier::store()` — bentuknya seperti apa | Tidak (Pembelian jalan tanpa ini, cuma rawan data dobel) |
| F-115 | Retur/Rusak wajib nunggu Inbound 'selesai' atau tidak | Tidak |
| F-116 | Batas atas qty Retur/Rusak — terhadap apa | Tidak |
| F-119 | Titik deduksi stok Penjualan — di Penjualan (sekarang) atau pindah ke Invoice (sesuai F-23) | **Ya, Fase C Penjualan** |
| F-120 | FIFO literal (butuh tabel lot baru) atau average-deduction | **Ya, Fase C Penjualan** |
| F-121 | Bangun ulang modul Perusahaan atau drop `perusahaan_id` dari rencana | **Ya, Fase B Penjualan** |
| F-122 | `driver_id`/`accounting_id` — teks bebas / `users` / drop | Tidak langsung blocking, tapi mempengaruhi skema `surat_jalans`/`invoices` |
| F-123 | Arsitektur Invoice — rantai 1:1 atau agregasi SPPG+tanggal | **Ya, paling blocking — menentukan skema `invoices`** |

---

## 6. File yang Diubah Sesi Ini

- `routes/auth.php`, `resources/js/pages/auth/login.tsx` — hapus route & link register
- Dihapus: `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/js/pages/auth/register.tsx`, `tests/Feature/Auth/RegistrationTest.php`
- `app/Http/Controllers/Pembelian/DaftarPembelanjaanController.php` — fix uniqueness re-check di `update()`
- Tidak ada file skema/kode Penjualan yang diubah (Fase A murni riset)
