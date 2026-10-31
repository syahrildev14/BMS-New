# Design: Modul Backup Data & Log Aktivitas (Sidebar Admin)

**Status:** Approved
**Tanggal:** 2026-10-31

## Tujuan

Tidak ada cara untuk admin melakukan backup database dari dalam aplikasi (satu-satunya cara saat ini adalah manual lewat server/CLI — risiko terbukti nyata di sesi ini saat `migrate:fresh` tidak sengaja menghapus data dev). Juga tidak ada halaman untuk melihat `audit_logs` yang sudah terus-menerus diisi oleh `RevisionGateService`, `SplitInvoiceService`, dll sejak awal — datanya ada tapi tidak bisa dilihat siapa pun lewat UI.

## Permission Baru

Ditambahkan ke katalog `RoleAndPermissionSeeder`:
- `system.backup.manage` — generate, download, hapus backup.
- `system.audit-log.view` — lihat daftar & detail audit log.

Keduanya otomatis masuk ke role `admin` (seeder sudah `'admin' => $permissions`, yaitu SEMUA kode permission). Tidak diberikan ke role lain secara default — sengaja, karena backup berisi seluruh isi database (termasuk password hash) dan audit log berisi jejak aktivitas semua user.

## Modul Backup Data

### Skema
Migration baru `backups`:
- `id`
- `filename` (string) — nama file fisik, mis. `backup_2026-10-31_153000.sqlite` / `.sql`
- `disk_path` (string) — path relatif di disk `local`
- `size_bytes` (unsigned big integer)
- `created_by` (FK users, restrictOnDelete)
- `created_at`/`updated_at`

### Service
`App\Services\BackupService`:
- `create(User $user): Backup` — deteksi `config('database.default')`:
  - `sqlite` → `File::copy(database_path('database.sqlite'), $targetPath)`.
  - `mysql` → jalankan `mysqldump` via `Symfony\Component\Process\Process` dengan kredensial dari `config('database.connections.mysql')`, output disimpan sebagai `.sql`. Kalau `mysqldump` tidak ditemukan di PATH, lempar exception dengan pesan jelas (bukan silent fail) — ini murni kebutuhan environment produksi, bukan dicoba jalan di dev sekarang (dev pakai sqlite).
  - Driver lain → `abort(422, 'Backup otomatis belum didukung untuk driver database ini.')`.
  - Simpan file ke `storage/app/backups/`, catat baris `Backup` baru dengan ukuran file aktual.
- `delete(Backup $backup): void` — hapus file fisik (`Storage::disk('local')->delete($backup->disk_path)`) lalu hapus baris DB. Dibungkus try/pastikan baris DB tidak terhapus kalau file gagal dihapus (konsistensi).

### Controller & Route
`App\Http\Controllers\Admin\BackupController` (`index`, `store`, `download`, `destroy`), route `admin/backups`, permission `system.backup.manage` di semua method (pola sama seperti resource admin lain).

### Frontend
`resources/js/pages/admin/backups/index.tsx`:
- Tombol **"Backup Sekarang"** (loading state saat proses, karena bisa beberapa detik untuk DB besar).
- Tabel riwayat: tanggal dibuat, ukuran (format KB/MB), dibuat oleh, aksi **Download** + **Hapus** (pakai `confirmDelete` yang sudah ada di `shared/lib/alerts.ts`).
- Reuse `Table`, `SortableTableHead`, `TablePagination` — pola identik halaman admin lain.

## Modul Log Aktivitas

### Controller & Route
`App\Http\Controllers\Admin\AuditLogController@index` — **read-only**, cuma `index`. Tidak ada create/update/destroy sama sekali (integritas audit trail). Route `admin/audit-logs`, permission `system.audit-log.view`.

Query: `AuditLog::with('user')->latest()->paginate(...)` — filter opsional by `action` dan rentang tanggal lewat query string (pola `?date=` yang sudah dipakai di halaman Pricing/Invoice).

### Frontend
`resources/js/pages/admin/audit-logs/index.tsx`:
- Tabel: Waktu, User, Action, Description — reuse `SortableTableHead`, `TableSearchInput`, `TablePagination`.
- Klik baris → `Dialog` (komponen yang sudah ada) menampilkan `old_data`/`new_data` dalam `<pre>` JSON terformat, untuk audit "siapa ubah apa".

## Sidebar

`resources/js/shared/constants/menu.config.ts` — tambah 2 entry baru di grup **Admin** yang sudah ada (sejajar "Role & Permission" dan "Pengguna"):
```ts
{ label: 'Backup Data', href: '/admin/backups', permission: 'system.backup.manage' },
{ label: 'Log Aktivitas', href: '/admin/audit-logs', permission: 'system.audit-log.view' },
```

## Testing

- Pest: `BackupService::create()` pada koneksi sqlite menghasilkan file fisik + baris `Backup` dengan `size_bytes` sesuai ukuran file aktual.
- Pest: `BackupController::destroy()` menghapus file fisik DAN baris DB; mencoba hapus backup yang file-nya sudah tidak ada tetap membersihkan baris DB (tidak 500).
- Pest: user tanpa `system.backup.manage` / `system.audit-log.view` mendapat 403 di semua route terkait.
- Pest: `AuditLogController@index` hanya mendaftarkan route `index` (tidak ada route store/update/destroy terdaftar untuk resource ini — cek lewat `route:list` atau assert route tidak ditemukan).

## Di Luar Scope (disepakati di sesi brainstorming)

- Backup terjadwal otomatis (cron) — hanya manual on-demand untuk sekarang.
- Restore dari backup lewat UI — sengaja tidak dibangun (restore adalah operasi berisiko tinggi, PRD/diskusi sesi ini tidak memintanya; kalau dibutuhkan, dilakukan manual oleh admin via server).
- Filter/kolom log teknis (`laravel.log`) — hanya `audit_logs` (aktivitas bisnis), bukan log error PHP.
