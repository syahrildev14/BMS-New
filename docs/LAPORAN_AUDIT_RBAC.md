# Laporan Audit RBAC & Perbaikan — Sistem BMS

> File laporan berdiri sendiri, TIDAK mengubah `FINDING_REGISTRY.md`. Penomoran F-105 s/d F-110 di bawah adalah **usulan lanjutan** dari jangkar F-1–F-104 di `FINDING_REGISTRY.md` — kalau Boss setuju, entry ini bisa dipindahkan ke sana secara manual. Sampai saat itu, dokumen ini adalah satu-satunya sumber untuk temuan & perbaikan sesi ini.

---

## 1. Konteks

Boss melaporkan sistem "ngebug" terkait RBAC. Dilakukan audit menyeluruh dari model → middleware → routing (`routes/web/*` vs `routes/api/*`) → seeder → frontend, lalu perbaikan langsung untuk semua temuan yang disetujui.

**Kesimpulan utama:** enforcement inti RBAC (yang di `FINDING_REGISTRY.md` masih tercatat **F-98 — OPEN**, "RBAC Fase C belum dikerjakan") ternyata **sudah selesai dikerjakan** di suatu titik sebelum sesi ini — dokumen registry-nya saja yang belum diupdate. Audit menemukan 4 gap yang tersisa **di luar** cakupan Fase C, semuanya sudah diperbaiki & diverifikasi.

---

## 2. Status Progres — Ringkasan Cepat

| # | Item | Status |
|---|---|---|
| 1 | Tombol Tambah/Edit/Hapus di UI tidak mengikuti permission granular | ✅ **Selesai diperbaiki** |
| 2 | Halaman web (`routes/web/*.php`) tidak digate permission (beda dari API) | ✅ **Selesai diperbaiki** |
| 3 | Celah eskalasi privilege lewat `/register` publik + seeder | ✅ **Selesai diperbaiki** |
| 4 | Komentar dokumentasi basi di `UserController`/`RoleController` | ✅ **Selesai diperbaiki** |
| 5 | Apakah `/register` publik memang perlu ada di ERP internal ini | ⏳ **Open — keputusan Boss** |

Semua perbaikan sudah diverifikasi (typecheck TS bersih, `route:list` dicek middleware-nya, seeder dijalankan & hasil di-cross-check ke DB dev). **Belum ada yang di-commit** — semua masih di working tree, menunggu review.

---

## 3. Temuan & Perbaikan Detail

### F-105 — RBAC Fase C dikonfirmasi sudah selesai (menutup F-98)
**Status: LOCKED (fakta)**

Middleware `can:<kode>` terpasang lengkap & konsisten di semua `routes/api/*.php` (index/show/store/update/destroy per resource, kode cocok 1:1 dengan katalog `RbacSeeder`). Seluruh `authorize()` di Form Request diarahkan ke `User::hasPermission()`. `Gate::before()` di `AppServiceProvider` sudah default-deny dengan benar (return `null`, bukan `false`, supaya user tanpa role tidak dianggap "sengaja ditolak"). Permission + `isSystemRole` di-share ke Inertia via `HandleInertiaRequests::share()`. Menu sidebar difilter via `menu.config.ts` + `filterMenuByPermissions()`.

Tidak ada perubahan di area ini — murni temuan bahwa pekerjaan Fase C sudah selesai.

---

### F-106 — UI tidak menegakkan permission granular (create/update/delete)
**Status: RESOLVED sesi ini**

**Sebelum:** Tombol "Tambah", ikon Edit, ikon Hapus muncul di SEMUA modul CRUD tanpa mengecek `auth.permissions` sama sekali — hanya visibilitas link di sidebar yang digate (F-105). User yang cuma punya permission `view` tetap melihat & bisa klik tombol create/update/delete, lalu ditolak backend dengan toast error generik ("Gagal menambahkan data" dsb). **Ini kemungkinan besar sumber utama kesan "sistem ngebug".**

**Perbaikan:**
- Hook baru `resources/js/shared/hooks/usePermissions.ts` — `usePermissions()` mengembalikan `{ can, permissions, isSystemRole }`.
- Diterapkan ke 11 modul CRUD yang sudah punya UI (34 file):
  - Master: `satuan`, `barang`, `supplier`, `gudang`, `armada`, `sppg`, `mitra`, `wilayah`, `jabatan`
  - RBAC: `role`, `user`
- Pola per modul: tombol "Tambah" hilang tanpa `<modul>.<resource>.create`; tombol Edit/Delete hilang individual tanpa `.update`/`.delete`; kolom aksi disembunyikan total kalau tidak ada keduanya.
- Guard bisnis lama tetap dipertahankan: `role.columns.tsx` tetap `disabled={is_system}` di Edit/Delete; `user.columns.tsx` tetap `disabled={id === currentUserId}` (tidak bisa hapus akun sendiri).
- Verifikasi: `npx tsc --noEmit -p tsconfig.json` — 0 error baru (2 error tersisa pre-existing & tidak terkait, di `pages/auth/register.tsx` & `reset-password.tsx`).

**Di luar scope (tidak ada yang perlu digate):** modul `bank-rekening`, `template-surat`, dan semua modul `pembelian/*`, `warehouse/*`, `penjualan/*` belum punya halaman frontend sama sekali (baru backend + API) — tidak ada tombol untuk digate.

---

### F-107 — Halaman web tidak digate permission, beda dengan API
**Status: RESOLVED sesi ini**

**Sebelum:** `routes/web/{master,pembelian,penjualan,warehouse,rbac}.php` hanya pakai middleware `auth`, tidak ada `can:`. Bandingkan `routes/api/*.php` yang sudah lengkap (F-105). Akibatnya user tanpa akses tetap bisa buka shell halaman lewat URL langsung (bookmark/riwayat/ketik manual) — link memang disembunyikan di sidebar, tapi halaman tetap "kebuka", baru gagal saat data-fetch ke API (403) tanpa penanganan khusus di `resources/js/shared/lib/axios.ts` (hanya status 419/401 yang ditangani secara eksplisit).

**Perbaikan:** middleware `can:<modul>.<resource>.view` ditambahkan ke seluruh route web per modul, termasuk pemetaan eksplisit untuk slug URL yang beda dari kode permission-nya:
- `order-penawaran` → `pembelian.list-order-penawaran.view`
- `retur` → `warehouse.retur-rusak.view`
- `stok-gudang` sengaja **tetap tanpa gate** (memang belum ada kode permission untuk resource ini, konsisten dengan catatan yang sudah ada di `menu.config.ts`)

Diverifikasi lewat `php artisan route:list -v` — dicek satu per satu bahwa middleware `can:` terpasang dengan kode yang benar di setiap route web.

---

### F-108 — 🔴 Celah eskalasi privilege lewat `/register` publik + seeder
**Status: RESOLVED sesi ini — TEMUAN KRITIS**

**Sebelum:** `/register` publik (guest, tanpa login) aktif dan membuat user baru **tanpa `role_id`**. `RbacSeeder` lama punya baris:
```php
User::whereNull('role_id')->update(['role_id' => $superadmin->id]);
```
yaitu: **semua** user ber-`role_id` null otomatis dinaikkan jadi **Superadmin** — dan baris ini jalan lagi setiap kali seeder dijalankan ulang (sesuai desain aslinya: ditambah permission baru ke katalog saat modul baru muncul, F-92). Konsekuensinya: siapa pun bisa mendaftar sendiri lewat `/register`, menunggu seeder berikutnya dijalankan (rutinitas normal saat development), lalu otomatis mendapat akses Superadmin penuh.

**Perbaikan:**
1. Role baru **"Belum Ditugaskan"** (`is_system=true`, `is_default=true`, 0 permission) menggantikan Superadmin sebagai role fallback yang aman.
2. `Superadmin.is_default` diubah jadi `false` — tidak lagi jadi role otomatis untuk siapa pun.
3. `RegisteredUserController::store()` sekarang eksplisit meng-assign role default saat registrasi — `role_id` tidak pernah lagi dibiarkan `null` menggantung.
4. `RoleController::store()`/`update()` dijaga supaya hanya **1 role** yang boleh `is_default=true` dalam satu waktu (sebelumnya tidak ada validasi ini — kalau admin bikin role baru dengan `is_default=true`, resolusi "role default" jadi ambigu antara 2+ role).
5. Sudah dijalankan `php artisan db:seed` di DB dev lokal (sqlite) dan diverifikasi lewat tinker: user existing lain (`syahril@skbukitbertam.com`, `ekojulian@gmail.com`) **tidak ikut ter-reassign** — hanya 2 akun demo dari `DatabaseSeeder` (`admin@gmail.com` → Superadmin, `user@deevatech.my.id` → Belum Ditugaskan) yang diatur ulang sesuai rencana.

---

### F-109 — Komentar dokumentasi basi di controller
**Status: RESOLVED sesi ini**

`UserController.php` dan `RoleController.php` masih punya komentar:
```php
/** TODO RBAC: rbac.user.view|create|update|delete */
```
padahal permission-nya **sudah** ditegakkan lewat middleware `can:` di `routes/api/rbac.php` (F-105). Komentar basi ini berisiko menyesatkan developer lain — bisa memicu penambahan cek duplikat, atau (lebih berbahaya) penghapusan middleware yang justru sudah benar karena dikira belum dipakai.

**Perbaikan:** komentar diperbarui untuk menunjuk ke lokasi enforcement yang sesungguhnya.

---

### F-110 — Apakah `/register` publik memang perlu ada?
**Status: OPEN — menunggu keputusan Boss**

Sistem ini adalah ERP internal — user sudah dikelola penuh lewat modul RBAC → User (F-85, CRUD user lengkap oleh admin). Self-registration publik terasa di luar model penggunaan internal-only, dan biasanya memang sisa boilerplate dari starter kit (Laravel React Starter Kit, F-49), bukan kebutuhan bisnis yang disengaja.

Celah keamanannya (F-108) **sudah ditutup** terlepas dari keputusan ini — jadi ini murni pertanyaan produk/arsitektur, bukan lagi risiko yang mendesak. Opsi:
- **Matikan total** `/register` (hapus route + link di halaman login) — paling konsisten dengan model "user dibuat admin lewat RBAC".
- **Pertahankan**, kalau memang ada rencana self-service onboarding di masa depan.

---

## 4. File yang Diubah Sesi Ini

**Backend:**
- `database/seeders/RbacSeeder.php` — role "Belum Ditugaskan", Superadmin tidak lagi default
- `database/seeders/DatabaseSeeder.php` — assign role eksplisit untuk 2 akun demo
- `app/Http/Controllers/Auth/RegisteredUserController.php` — assign role default saat registrasi
- `app/Http/Controllers/Rbac/RoleController.php` — guard `is_default` tunggal + komentar diperbarui
- `app/Http/Controllers/Rbac/UserController.php` — komentar diperbarui
- `routes/web/master.php`, `routes/web/pembelian.php`, `routes/web/penjualan.php`, `routes/web/warehouse.php`, `routes/web/rbac.php` — tambah middleware `can:<kode>.view`

**Frontend:**
- `resources/js/shared/hooks/usePermissions.ts` (baru)
- 33 file di 11 modul (`*Page.tsx`, `components/*Table.tsx`, `constants/*.columns.tsx`) untuk Master (satuan, barang, supplier, gudang, armada, sppg, mitra, wilayah, jabatan) dan RBAC (role, user)

**Database (dev lokal, sqlite):**
- `php artisan db:seed` sudah dijalankan — role "Belum Ditugaskan" dibuat, `admin@gmail.com`/`user@deevatech.my.id` di-assign ulang sesuai rencana

---

## 5. Yang Belum Dilakukan / Perlu Keputusan Boss

- **F-110** (di atas) — keputusan matikan/pertahankan `/register` publik.
- **Tidak ada commit** — semua perubahan masih di working tree lokal, menunggu review Boss sebelum di-commit/push.
- Migrasi `is_default` sebagai kolom unik di level database (saat ini cuma dijaga di level aplikasi/controller, bukan constraint DB) — bisa jadi hardening tambahan kalau Boss mau, di luar scope audit ini.
