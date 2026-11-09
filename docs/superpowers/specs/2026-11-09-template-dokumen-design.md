# Desain: Master Template Dokumen (Surat Jalan, Tanda Terima, Invoice)

**Tanggal:** 2026-11-09
**Status:** Menunggu tinjauan

## 1. Tujuan

Koperasi dapat mengunggah format dokumen sendiri dalam file Word (`.docx`), lalu Ops dan Finance memilih format itu setiap kali membuat dokumen. Sistem mengisi data ke dalam template dan menghasilkan PDF.

Contoh format awal ada di `docs/Surat Jalan.docx`, `docs/Surat Tanda Terima.docx`, dan `docs/invoice.docx`. Ketiganya memakai penanda `${nama_penanda}`.

## 2. Keputusan

| Topik | Keputusan |
|---|---|
| Waktu memilih template | Setiap generate dokumen, bukan saat order. Satu Surat Jalan bisa menggabungkan beberapa order |
| Cakupan pilihan | Satu proses generate memakai satu template untuk semua dokumen yang dihasilkan |
| Hasil | PDF, dari `.docx` terisi yang dikonversi LibreOffice |
| Armada dan driver | Dua master terpisah, nilainya disalin ke dokumen |
| Rekening bank | Master sendiri, dipilih saat generate invoice, nilainya disalin ke invoice |
| Penggantian format | File template tidak bisa ditimpa. Upload template baru dan nonaktifkan yang lama |

## 3. Di luar cakupan

- Editor template di browser.
- Template untuk tagihan supplier, karena fitur ini tidak menghasilkan PDF.
- Template untuk PDF Tanda Terima hasil STT. PDF ini tetap memakai `pdf.tanda-terima`.
- Template default per Dapur.
- Penggabungan beberapa template dalam satu PDF.

## 4. Data

### Tabel baru

**`document_templates`**: `name`, `document_type` (`SURAT_JALAN`, `TANDA_TERIMA`, `INVOICE`), `file_path`, `original_filename`, `placeholders` (JSON), `is_active`, `uploaded_by`.

**`armadas`**: `name`, `plate_number`, `is_active`.

**`drivers`**: `name`, `is_active`.

**`bank_accounts`**: `bank_name`, `account_number`, `account_holder`, `is_active`.

### Kolom baru

**`surat_jalans`**: `document_template_id` (nullable, FK restrict), `armada_name`, `plate_number`, `driver_name` (nullable).

**`invoices`**: `document_template_id` (nullable, FK restrict), `bank_name`, `bank_account`, `bank_owner`, `issued_at` (nullable). `issued_at` menjaga `invoice_date` tetap sama saat PDF dibuat ulang.

Baris dengan `document_template_id` kosong tetap memakai view PDF yang sekarang.

### Hak akses

| Izin baru | Role | Fungsi |
|---|---|---|
| `document-templates.manage` | admin | Kelola Template Dokumen |
| `fleet.manage` | ops, admin | Kelola Armada dan Driver |
| `bank-accounts.manage` | finance, admin | Kelola Rekening Bank |

Menu baru berada di grup Master Data. `RoleAndPermissionSeeder` perlu dijalankan sekali saat deploy.

## 5. Daftar penanda

Upload ditolak bila ada penanda di luar daftar jenis dokumennya, atau bila penanda wajib tidak ada.

### Surat Jalan

| Penanda | Wajib | Nilai |
|---|---|---|
| `nomor_surat_jalan` | Ya | `sj_number` |
| `item_no` | Ya | Nomor urut baris |
| `item_nama_barang` | Ya | Nama barang |
| `item_qty` | Ya | `qty_ordered` |
| `item_satuan` | Ya | Nama satuan |
| `item_keterangan` | Tidak | Nomor order dan tanggal kebutuhan |
| `sppg_name` | Tidak | Nama Dapur |
| `tanggal` | Tidak | Tanggal kirim |
| `no_po` | Tidak | Nomor order unik, dipisah koma |
| `armada_name` | Tidak | Salinan nama armada |
| `no_pol` | Tidak | Salinan nomor polisi |
| `driver_name` | Tidak | Salinan nama driver |

### Tanda Terima

Penanda `item_*`, `sppg_name`, `tanggal`, dan `no_po` sama seperti Surat Jalan, dengan `item_*` kecuali `item_keterangan` wajib. Tambahan: `nomor_tanda_terima` (tidak wajib, berisi `sj_number`).

### Invoice

| Penanda | Wajib | Nilai |
|---|---|---|
| `invoice_number` | Ya | Nomor invoice |
| `no` | Ya | Nomor urut baris |
| `nama_barang` | Ya | Nama barang |
| `qty` | Ya | `qty_received - qty_rejected` |
| `satuan` | Ya | Nama satuan |
| `harga_satuan` | Ya | `final_selling_price` |
| `harga_total` | Ya | Qty lolos QC × harga satuan |
| `grand_total` | Ya | `invoices.total_amount` |
| `sppg_name` | Tidak | Nama Dapur |
| `phone` | Tidak | `dapurs.pic_phone` |
| `address` | Tidak | Alamat Dapur |
| `po_number` | Tidak | Nomor order |
| `delivery_date` | Tidak | Tanggal kirim |
| `invoice_date` | Tidak | Tanggal persetujuan invoice |
| `bank_name`, `bank_account`, `bank_owner` | Tidak | Salinan data rekening |
| `total_barang` | Tidak | Jumlah semua `harga_total` |
| `sudah_dibayar` | Tidak | Jumlah invoice `PAID` sebelumnya untuk pasangan order dan supplier |

Format nilai: uang memakai `Format::rupiah`, qty memakai `Format::qty`, tanggal memakai format Indonesia `d F Y`. Nilai yang kosong diisi string kosong.

## 6. Alur

### Template

- Validasi upload: `.docx`, maksimal 5 MB, file ZIP valid, memuat `word/document.xml`.
- File disimpan di disk privat dan tidak pernah bisa ditimpa.
- Template yang pernah dipakai dokumen tidak bisa dihapus, hanya dinonaktifkan.
- Template yang dinonaktifkan tidak muncul di pilihan generate, tetapi tetap dipakai untuk merender dokumen yang sudah memilihnya, termasuk draft invoice.

### Generate Surat Jalan dan Tanda Terima

- Selama belum ada template aktif untuk jenis itu, `document_template_id` boleh kosong dan sistem memakai view PDF sekarang.
- Setelah ada template aktif, template wajib dipilih.
- Armada wajib bila template memuat `armada_name` atau `no_pol`.
- Driver wajib bila template memuat `driver_name`.
- Nilai armada dan driver disalin ke `surat_jalans` di dalam transaksi generate.
- Template Surat Jalan hanya menghasilkan halaman Surat Jalan, tanpa halaman form Tanda Terima.

### Generate Invoice

- Aturan template sama seperti pengiriman, memakai jenis `INVOICE`.
- Rekening wajib bila template memuat salah satu penanda `bank_*`.
- Template dan rekening disalin ke setiap invoice yang dibuat atau direvisi pada proses itu. Invoice final yang totalnya tidak berubah tidak disentuh.
- Form generate ulang terisi otomatis dari pilihan sebelumnya dan bisa diganti.
- Bila ada invoice `PAID` untuk pasangan order dan supplier sehingga yang dibuat adalah invoice tambahan, template wajib memuat `total_barang` dan `sudah_dibayar`. Bila tidak, generate ditolak dengan `ValidationException` dan seluruh transaksi dibatalkan.

### Render PDF

1. Salin template ke folder sementara.
2. Isi nilai dengan `TemplateProcessor` milik PHPWord, escape output aktif, dan `cloneRow` untuk baris barang.
3. Jalankan `soffice --headless --norestore -env:UserInstallation=file:///tmp/lo-<uuid> --convert-to pdf`, batas waktu 60 detik.
4. Simpan PDF ke path yang sama seperti sekarang, lalu hapus folder sementara.

Waktu render tidak berubah: pengiriman setelah transaksi commit, invoice setelah disetujui.

## 7. Komponen

| Unit | Tanggung jawab |
|---|---|
| `App\Services\DocumentTemplateService` | Baca penanda, validasi upload, simpan, nonaktifkan, hapus |
| `App\Support\DocumentPlaceholders` | Daftar penanda dan penanda wajib per jenis |
| `App\Services\DocumentDataBuilder` | Mengubah `SuratJalan` atau `Invoice` menjadi nilai penanda dan baris |
| `App\Services\DocumentRenderService` | Mengisi `.docx` dan meminta konversi PDF |
| `App\Contracts\PdfConverter`, `App\Services\LibreOfficePdfConverter` | Konversi `.docx` ke PDF, dapat dipalsukan di tes |
| Controller Admin | `DocumentTemplateController`, `ArmadaController`, `DriverController`, `BankAccountController` |

Perubahan pada kode yang ada:

- `DeliveryNoteService::generateDocuments()` dan `Ops\DeliveryNoteController` menerima pilihan template, armada, dan driver.
- `SplitInvoiceService::generateInvoicesForOrder()` dan `Finance\InvoiceController` menerima pilihan template dan rekening.
- `GenerateSuratJalanPdfJob` dan `GenerateInvoicePdfJob` merender dari template bila `document_template_id` terisi.
- Halaman Pengiriman dan Invoice mendapat pilihan baru.
- `composer.json` ditambah `phpoffice/phpword`.
- `Dockerfile` ditambah `libreoffice-writer` dan paket font yang kompatibel dengan font Office.

## 8. Penanganan kegagalan

| Kondisi | Perilaku |
|---|---|
| Render pengiriman gagal | Dokumen dan stok tetap tersimpan, error dicatat, PDF dibuat ulang saat diunduh lewat `ensurePdf()` |
| Render invoice gagal | Invoice tetap `ISSUED`, error dicatat, Finance memakai tombol buat ulang PDF |
| Konversi melewati 60 detik | Diperlakukan sebagai render gagal |
| Template, armada, driver, atau rekening nonaktif saat dikirim | `ValidationException` dengan pesan jelas |
| Upload tidak valid | `ValidationException` yang menyebut penanda bermasalah |

## 9. Pengujian

Tes otomatis, dengan `PdfConverter` dipalsukan:

- Pembacaan penanda, penolakan penanda asing, dan penanda wajib.
- Ketiga file contoh di `docs` lolos validasi.
- Pengisian nilai dan perulangan baris pada `.docx` hasil.
- Karakter `&` pada nama barang tidak merusak file.
- Salinan armada, driver, dan rekening tidak berubah setelah master diedit.
- Masa peralihan tanpa template aktif.
- Template wajib setelah ada template aktif.
- Penolakan invoice tambahan pada template tanpa `sudah_dibayar`.
- Render gagal tidak membatalkan dokumen.
- Template terpakai tidak bisa dihapus.
- Hak akses tiap menu.

`docs/SKENARIO_UJI_COBA.md` ditambah grup uji template untuk memeriksa konversi LibreOffice dan tata letak PDF di staging.

## 10. Dokumen yang diperbarui

`docs/PRD.md` Modul 3 dan 4: template dokumen, master armada, driver, dan rekening, serta Surat Jalan berbasis template yang tidak lagi menyertakan form Tanda Terima.

## 11. Pekerjaan pada file template

Dilakukan koperasi sebelum dipakai untuk kasus terkait:

- `invoice.docx`: tambah baris `${total_barang}` dan `${sudah_dibayar}` agar bisa dipakai untuk invoice tambahan.
- `Surat Tanda Terima.docx`: tambah `${nomor_tanda_terima}` agar dokumen fisik bisa dicocokkan dengan sistem.
