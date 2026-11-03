# DEPLOYMENT: BMS — Docker + Cloudflare Tunnel (shared dengan task-management)

Target: **https://bms.deevatech.my.id**

> VPS ini sudah menjalankan stack `task-management` (dengan **cloudflared** —
> tunnel aktif) dan stack `absensi`. BMS jalan sebagai stack Docker Compose
> **terpisah** di `/opt/apps/bms`, dan **memakai tunnel `cloudflared` yang sudah
> ada** — tidak bikin tunnel/container `cloudflared` baru, tidak perlu nginx
> host maupun certbot (SSL ditangani Cloudflare).

Berbeda dengan absensi, BMS **memakai queue dan scheduler**, jadi stack-nya 5 container:

| Container | Fungsi |
|---|---|
| `bms-app` | PHP 8.3-FPM (Laravel) |
| `bms-nginx` | Web server; join `shared-network` (target tunnel) + bind `127.0.0.1:8083` untuk cek lokal |
| `bms-mysql` | MySQL 8.0, data di volume `bms_mysql_data`, tidak diekspos |
| `bms-queue` | Worker antrean — generate PDF Surat Jalan, Tanda Terima, Invoice |
| `bms-scheduler` | `schedule:work` — `finance:mark-overdue` setiap 00:05 WIB |

**Cara nyambung:** `bms-nginx` ikut network eksternal `shared-network` yang juga
sudah di-join-kan ke container `cloudflared`. Cloudflare Tunnel di-route ke
`bms-nginx:80` lewat network itu — tanpa expose port apa pun ke publik.

---

## 0. Sekali saja: pastikan cloudflared terhubung ke shared-network

Kalau absensi sudah jalan via tunnel, langkah ini biasanya **sudah beres**. Cek:

```bash
docker network inspect shared-network --format '{{range .Containers}}{{.Name}}{{"\n"}}{{end}}'
```

Kalau container `cloudflared` belum ada di daftar:

```bash
docker ps --format '{{.Names}}\t{{.Image}}' | grep -i cloudflared
docker network inspect shared-network >/dev/null 2>&1 || docker network create shared-network
docker network connect shared-network <NAMA_CONTAINER_CLOUDFLARED>
```

🔴 Catatan durabilitas: kalau container `cloudflared` di-*recreate* (`docker compose down && up` di stack task-management), koneksi manual ini hilang dan perlu diulang — BMS **dan** absensi akan sama-sama down (error 502 dari Cloudflare). Supaya permanen, tambahkan `shared-network` (`external: true`) ke service `cloudflared` di `docker-compose.yml` task-management. `deploy.sh` akan menampilkan peringatan kalau cloudflared tidak terdeteksi di network ini.

---

## 1. Clone & konfigurasi env

```bash
sudo mkdir -p /opt/apps/bms && sudo chown -R $USER:$USER /opt/apps/bms
cd /opt/apps/bms
git clone git@github.com:syahrildev14/BMS-New.git .
chmod +x deploy.sh
cp .env.production.example .env
id -u; id -g          # catat untuk HOST_UID / HOST_GID
nano .env
```

Isi/pastikan nilai berikut di `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bms.deevatech.my.id

HOST_UID=<hasil id -u>
HOST_GID=<hasil id -g>
APP_PORT=8083

DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=bms
DB_USERNAME=bms
DB_PASSWORD=<password-kuat-baru>
DB_ROOT_PASSWORD=<password-root-kuat-baru>
```

> Generate password acak: `openssl rand -base64 24`

🔴 `DB_PASSWORD`/`DB_ROOT_PASSWORD` langsung dipakai sebagai `MYSQL_PASSWORD`/`MYSQL_ROOT_PASSWORD` oleh service `mysql` — cukup ubah di `.env`. Nilainya hanya berlaku saat volume MySQL **pertama kali** dibuat; mengganti setelahnya tidak mengubah password di dalam MySQL.

🔴 `HOST_UID`/`HOST_GID`: user `www-data` di dalam image di-set ke UID/GID user VPS, sehingga file di bind mount (`storage/`, `bootstrap/cache`, `vendor/`) dimiliki user Anda sendiri. Karena itu, **tidak perlu** `sudo chown www-data` seperti di absensi.

---

## 2. Build & jalankan

```bash
cd /opt/apps/bms
./deploy.sh --first
```

Yang dilakukan script:
1. Pastikan `shared-network` ada + cek cloudflared terhubung
2. `docker compose build app`
3. Start `mysql`, tunggu sampai `healthy`, lalu start `app` + `nginx`
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build` di container `node:20` sementara
6. `key:generate` (jika `APP_KEY` kosong) + `storage:link`
7. `migrate --force`
8. Seed **hanya** `RoleAndPermissionSeeder` — data dummy tidak ikut (`DatabaseSeeder` juga menolak jalan di production)
9. `optimize` (cache config/route/view/event)
10. Start/restart `queue` + `scheduler`

Kenapa `composer install`/`npm run build` dijalankan setelah container hidup: `vendor/` dan `public/build` datang dari bind mount host (`.:/var/www/html`), jadi hasil build di image akan tertutup mount tersebut.

Cek lokal di VPS:

```bash
docker compose ps                      # semua Up / healthy
curl -I http://127.0.0.1:8083/up       # harus HTTP 200
```

---

## 3. Cloudflare Zero Trust Dashboard — tambah route, TANPA halaman login

1. **Networks → Tunnels** → pilih tunnel yang dipakai task-management (status aktif).
2. Tab **Public Hostname → Add a public hostname**:
   - **Subdomain**: `bms`
   - **Domain**: `deevatech.my.id`
   - **Service → Type**: `HTTP`
   - **Service → URL**: `bms-nginx:80`
3. **Save**. DNS record `bms` dibuat otomatis oleh Cloudflare — tidak perlu tambah record A manual.

🔴 Jangan bikin **Access Application** untuk `bms.deevatech.my.id` di **Access → Applications** (kecuali memang ingin ada login Cloudflare di depan aplikasi). Kalau ada Access Application wildcard `*.deevatech.my.id`, hostname ini ikut terproteksi — tambahkan bypass untuk `bms.deevatech.my.id`.

🔴 Upload file: paket Cloudflare Free membatasi body request 100 MB — jauh di atas batas BMS (25 MB), jadi aman.

---

## 4. Buat akun admin pertama

Akun dummy dari seeder tidak ada di produksi, jadi buat manual:

```bash
docker compose exec app php artisan tinker
```

```php
$u = App\Models\User::create([
    'name' => 'Administrator',
    'email' => 'admin@deevatech.my.id',
    'password' => bcrypt('PASSWORD_KUAT_ANDA'),
]);
$u->assignRole('admin');
exit
```

Login, lalu input data master (satuan, supplier, dapur, gudang, item) lewat aplikasi.

---

## 5. Checklist verifikasi

- [ ] `https://bms.deevatech.my.id` terbuka langsung (tanpa halaman login Cloudflare Access), SSL valid
- [ ] Tidak ada error *mixed content* di console browser, CSS/JS tampil normal
- [ ] Login admin berhasil
- [ ] Generate Surat Jalan / Invoice → PDF terbuat (`docker compose logs -f queue`)
- [ ] `docker compose exec app php artisan schedule:list` → `finance:mark-overdue` terdaftar
- [ ] Menu Backup → buat backup berhasil (`mysqldump` tersedia di image)
- [ ] `docker network inspect shared-network` → cloudflared, `absensi-nginx`, **dan** `bms-nginx` terhubung
- [ ] `https://task.deevatech.my.id` dan `https://absensi.deevatech.my.id` tetap normal
- [ ] Dari luar, `http://<ip-vps>:8083` **harus gagal connect** (nginx BMS hanya bind ke `127.0.0.1`)

---

## 6. Update kode saat deploy berikutnya

```bash
cd /opt/apps/bms
./deploy.sh
```

Script menjalankan `git pull --ff-only`, build ulang image, composer, build asset, migrate, cache ulang, dan restart `app` + `queue` + `scheduler`. **Tidak** menjalankan seeder. Tidak ada yang perlu diubah di sisi Cloudflare.

Jika ada permission baru di `RoleAndPermissionSeeder` (aman dijalankan berulang):

```bash
docker compose exec app php artisan db:seed --class=RoleAndPermissionSeeder --force
docker compose exec app php artisan permission:cache-reset
```

---

## 7. Backup database

Backup dari menu aplikasi tersimpan di `/opt/apps/bms/storage/app/private/backups/` — masih di VPS yang sama, jadi salin berkala ke tempat lain (`scp`/`rsync` ke lokal atau cloud storage).

Backup manual:

```bash
cd /opt/apps/bms
source <(grep -E '^DB_(DATABASE|ROOT_PASSWORD)=' .env)
docker compose exec -T mysql mysqldump -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > ~/bms_$(date +%F).sql.gz
```

Restore:

```bash
gunzip -c ~/bms_YYYY-MM-DD.sql.gz | docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"
```

---

## Perintah yang sering dipakai

```bash
docker compose ps                         # status container
docker compose logs -f app nginx          # log web
docker compose logs -f queue              # log worker PDF
tail -f storage/logs/laravel-*.log        # log Laravel
docker compose exec app php artisan ...   # jalankan artisan
docker compose restart queue scheduler    # restart worker
docker compose down                       # matikan (data MySQL aman di volume)
```

> **Jangan** jalankan `docker compose down -v` — `-v` menghapus volume `bms_mysql_data` (seluruh database).
>
> **Jangan** jalankan `php artisan migrate:fresh` / `db:wipe` / `db:seed` (tanpa `--class`) di produksi.

## Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| Cloudflare error **502 / Bad gateway** | cloudflared tidak bisa menjangkau `bms-nginx`. Cek langkah 0 (`docker network connect ...`) dan `docker compose ps nginx` |
| Cloudflare error **1033** | Tunnel task-management mati — cek container cloudflared |
| Muncul halaman login Cloudflare | Ada Access Application (wildcard) yang mencakup `bms.deevatech.my.id` — lihat langkah 3 |
| `network shared-network declared as external, but could not be found` | `docker network create shared-network`, lalu ulangi langkah 0 |
| `Permission denied` di `storage/` / `bootstrap/cache` | `HOST_UID/HOST_GID` di `.env` salah. Perbaiki → `docker compose build app && docker compose up -d` |
| CSS/JS tidak muncul / mixed content / redirect loop | Pastikan `APP_URL=https://bms.deevatech.my.id`, lalu `docker compose exec app php artisan optimize` |
| `/build/manifest.json` not found | Build Vite gagal — ulangi `./deploy.sh`, perhatikan tahap "Build asset frontend" |
| PDF tidak pernah terbuat | `docker compose logs queue`; job gagal: `docker compose exec app php artisan queue:failed` |
| Perubahan `.env` tidak berefek | Config ter-cache: `docker compose exec app php artisan optimize && docker compose restart app queue scheduler` |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL belum siap / `DB_HOST` bukan `mysql` — `docker compose ps mysql` |
