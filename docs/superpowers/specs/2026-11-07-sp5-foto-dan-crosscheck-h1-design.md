# Sub-proyek 5 — Foto Kedatangan & Crosscheck H+1

**Tanggal:** 2026-11-07
**Status:** Disetujui (desain)

## Keputusan bisnis
- Dapur mengunggah foto **per dokumen pengiriman** (Surat Jalan / Tanda Terima).
- Crosscheck H+1 dilakukan koperasi (Ops) dan menjadi **syarat pembuatan invoice**.
- Cek bersama akuntan dapur **tidak dicatat** di sistem.

## Data
- `delivery_document_photos`: id, surat_jalan_id (FK cascade), path, original_name, uploaded_by (FK users), timestamps.
- `surat_jalans`: verification_status enum(`PENDING`, `VERIFIED`, `DISCREPANCY`) default `PENDING`, verification_notes (text nullable), verified_by (FK users nullable), verified_at (datetime nullable).
- Permission `delivery-verifications.manage` (migration aditif: role `ops` & `admin`; juga di seeder).

## Foto (Dapur)
- JPG/PNG/WebP, maksimal 5 MB per file, maksimal 10 foto per dokumen; disimpan di disk privat `local`.
- Dapur pemilik dokumen & pemegang permission verifikasi bisa melihat; dapur lain 403.
- Dapur boleh menghapus foto selama dokumen belum `VERIFIED`; upload juga ditolak setelah `VERIFIED`.

## Crosscheck (Ops)
- Daftar dokumen (hanya `supplier_id` terisi): belum terverifikasi / terverifikasi; penanda tanggal kirim sudah lewat; progres STT; jumlah foto.
- Detail: per baris qty pesan, qty diterima/ditolak, alasan tolak, qty nota (khusus TT basah), foto, peringatan bila belum ada foto.
- **Terverifikasi**: ditolak bila ada baris tanpa `qty_received`.
- **Ada Selisih**: catatan wajib. Dari `VERIFIED` ini berarti membuka kembali:
  - ditolak bila ada invoice atau tagihan supplier `ISSUED`/`OVERDUE`/`PAID` untuk (order, supplier) baris dokumen;
- Setiap status **Ada Selisih** mem-`VOID` invoice dan tagihan supplier `DRAFT` pasangan tersebut agar tidak disetujui dengan angka lama.
- Semua aksi tercatat di `audit_logs`.

## Aturan pengikat
- `SplitInvoiceService::generateInvoicesForOrder` dan `approveInvoice`: ditolak bila ada baris order pada dokumen ber-`supplier_id` yang belum `VERIFIED`; pesan menyebut nomor dokumen.
- Dokumen lama (`supplier_id` NULL) dikecualikan.
- `OrderController::submitStt`: ditolak bila barisnya ada di dokumen `VERIFIED`; baris dokumen dikunci `lockForUpdate()` terhadap verifikasi yang berjalan bersamaan.

## Pengujian
Unggah foto + scoping; validasi file; batas 10 foto; verifikasi butuh STT lengkap; selisih wajib catatan; guard invoice; dokumen lama dikecualikan; STT terkunci & terbuka kembali; buka kembali ditolak setelah invoice terbit; draft invoice di-VOID saat dibuka kembali; permission.
