# Desain: Warna Tema Sistem

**Tanggal:** 2026-11-09
**Status:** Menunggu tinjauan

## 1. Tujuan

Admin dapat mengganti warna utama aplikasi tanpa mengubah kode. Satu warna berlaku untuk seluruh user dan halaman login.

## 2. Keputusan

| Topik | Keputusan |
|---|---|
| Yang menentukan warna | Admin, satu warna untuk seluruh sistem |
| Bagian yang berubah | Satu warna utama. Warna status tetap |
| Cara memilih | Enam warna siap pakai, pemilih warna, dan kolom kode hex |
| Penerapan di kode | Semua biru yang menjadi aksen dialihkan ke token tema |
| Tombol hapus | Bergaris merah dengan latar polos, untuk semua warna tema |

## 3. Di luar cakupan

- Warna per user.
- Warna sidebar, latar halaman, kartu, dan teks.
- Warna pada PDF dan template dokumen.
- Warna status: hijau, kuning, merah, dan badge status biru.
- Logo dan favicon.

## 4. Data

**Tabel baru `app_settings`:** `id`, `key` (unique), `value` (text, nullable), timestamps.

Warna tersimpan pada key `primary_color` dalam format `#RRGGBB` huruf besar. Bila baris tidak ada atau nilainya tidak valid, sistem memakai `#2563EB`.

Nilai di-cache selamanya dengan key `appearance.primary_color`. Cache dihapus setelah transaksi simpan atau reset berhasil.

### Hak akses

| Izin baru | Role | Fungsi |
|---|---|---|
| `appearance.manage` | admin | Mengubah warna tema |

`RoleAndPermissionSeeder` perlu dijalankan sekali saat deploy.

## 5. Aturan warna

Warna siap pakai:

| Nama | Kode |
|---|---|
| Biru (bawaan) | `#2563EB` |
| Merah Garuda | `#DC2626` |
| Hijau | `#15803D` |
| Ungu | `#7C3AED` |
| Oranye | `#C2410C` |
| Teal | `#0F766E` |

Validasi di server berlaku untuk semua warna:

- Format harus `#RRGGBB`.
- Kontras minimal 3:1 terhadap `#FFFFFF` dan terhadap `#111827`, memakai rumus kontras WCAG.

Warna teks di atas warna utama adalah `#FFFFFF` atau `#000000`, memilih yang kontrasnya lebih tinggi.

Validasi di halaman hanya untuk pesan langsung. Server tetap menjadi penentu.

## 6. Alur

### Halaman Admin → Tampilan

- Menampilkan warna aktif, enam warna siap pakai, pemilih warna, dan kolom kode hex.
- Memilih warna langsung mengubah tampilan halaman itu sebagai pratinjau. Warna belum berlaku bagi user lain.
- Tombol Simpan menyimpan warna.
- Tombol Kembali ke Bawaan menghapus pengaturan sehingga sistem memakai `#2563EB`.
- Meninggalkan halaman tanpa menyimpan membatalkan pratinjau.
- Simpan dan reset menulis `AuditLog` dalam transaksi yang sama, dengan warna lama dan baru.

### Penerapan

`app.blade.php` menulis satu blok `<style>` sebelum aset Vite:

```html
<style>:root{--brand:#2563EB;--brand-foreground:#FFFFFF}</style>
```

Nilainya sudah melewati pemeriksaan format hex, sehingga tidak bisa menyisipkan CSS.

Warna juga dibagikan lewat shared props Inertia dan diterapkan `AppLayout` saat berpindah halaman, sehingga user yang sedang membuka aplikasi mendapat warna baru tanpa memuat ulang. Hal yang sama membatalkan pratinjau yang belum disimpan.

`app.css` menurunkan token dari `--brand`, dengan cadangan `var(--brand, #2563EB)`:

| Token | Nilai |
|---|---|
| `--primary` | Warna utama, sama pada mode terang dan gelap |
| `--primary-foreground` | `--brand-foreground` |
| `--primary-hover` | Campuran warna utama dengan 12% hitam |
| `--primary-active` | Campuran warna utama dengan 24% hitam |
| `--primary-soft` | Mode terang: 10% warna utama dengan putih. Mode gelap: 25% warna utama dengan latar gelap |
| `--primary-text` | Mode terang: campuran 15% hitam. Mode gelap: campuran 35% putih |
| `--primary-glow` | Warna utama dengan transparansi, untuk bayangan dan gradien |

Campuran warna memakai `color-mix()`.

## 7. Komponen

| Unit | Tanggung jawab |
|---|---|
| `App\Models\AppSetting` | Model tabel pengaturan |
| `App\Services\AppearanceService` | Daftar warna, validasi kontras, baca warna, simpan, reset, warna teks |
| `App\Http\Controllers\Admin\AppearanceController` | Halaman, simpan, reset |
| `resources/js/pages/admin/appearance/index.tsx` | Halaman pengaturan dan pratinjau |

Perubahan pada kode yang ada:

- `resources/css/app.css`: token tema.
- `resources/views/app.blade.php`: blok warna.
- `routes/web.php`, `menu.config.ts`, `RoleAndPermissionSeeder`: rute, menu, dan izin.
- Komponen `button`, `input`, `select`, `checkbox`, `combobox`, `dropdown-menu`, `table`, `avatar`, `SettingsTabs`, `DataTable`, `StatTile`.
- `alerts.ts`, `AppLayout`, `auth-split-layout`, `DashboardPage`, dan tautan biru di halaman modul.
- `button.tsx` varian `destructive`: garis merah, latar polos, teks merah.

`badge.tsx` tidak berubah karena warna birunya menyatakan status.

## 8. Penanganan kegagalan

| Kondisi | Perilaku |
|---|---|
| Format hex salah | `ValidationException` dengan pesan format |
| Kontras kurang | `ValidationException` yang menjelaskan warna terlalu terang atau terlalu gelap |
| User tanpa izin | 403 |
| Pengaturan belum ada | Memakai `#2563EB` |
| Nilai tersimpan tidak valid | Memakai `#2563EB` |

## 9. Pengujian

Tes otomatis:

- Admin dapat menyimpan warna siap pakai dan warna bebas yang lolos.
- User tanpa izin mendapat 403.
- Format salah dan kontras kurang ditolak.
- Warna teks terpilih sesuai kontras.
- Warna tersimpan muncul di HTML halaman login.
- Sistem memakai warna bawaan bila belum diatur atau nilainya tidak valid.
- Reset mengembalikan warna bawaan dan menghapus cache.
- Simpan menulis `AuditLog`.

Pengecekan manual di staging: tombol, tautan, input, checkbox, tab, menu aktif, popup, tabel, dan tombol hapus pada mode terang dan gelap.

## 10. Dokumen yang diperbarui

`docs/PRD.md`: pengaturan warna tema dan izin `appearance.manage`.
