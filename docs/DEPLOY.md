# DEPLOYMENT: BMS — Docker + Cloudflare Tunnel (shared dengan task-management)

Target: **https://bms.deevatech.my.id**

> VPS ini sudah menjalankan stack `task-management` (dengan **cloudflared** —
> tunnel aktif) dan stack `absensi`. BMS jalan sebagai stack Docker Compose
> **terpisah** di `/opt/apps/bms`, dan **memakai tunnel `cloudflared` yang sudah
> ada** — tidak bikin tunnel/container `cloudflared` baru, tidak perlu nginx
> host maupun certbot (SSL ditangani Cloudflare).

BMS **memakai queue dan scheduler**, jadi stack-nya 5 container:

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

## Ringkasan alur deploy pertama

| # | Langkah | Di mana |
|---|---|---|
| 1 | Cek prasyarat (Docker, akses GitHub, cloudflared di `shared-network`) | VPS |
| 2 | Clone repo ke `/opt/apps/bms` | VPS |
| 3 | Buat & isi `.env` (UID/GID, password DB, `APP_KEY`) | VPS |
| 4 | Jalankan `./deploy.sh --first` | VPS |
| 5 | Tambah Public Hostname `bms` di tunnel | Cloudflare dashboard |
| 6 | Buat akun admin pertama | VPS |
| 7 | Checklist verifikasi | Browser + VPS |

Semua perintah di bawah dijalankan sebagai **user biasa VPS** (bukan root), kecuali yang memakai `sudo`.

---

## 1. Prasyarat (sekali saja)

### 1a. Docker & akses GitHub

```bash
docker --version && docker compose version   # keduanya harus keluar versi
groups | grep -q docker && echo "OK: user ada di grup docker"
ssh -T git@github.com                         # harus: "Hi ...! You've successfully authenticated"
```

- Kalau `docker` minta `sudo`: `sudo usermod -aG docker $USER`, lalu **logout & login ulang**.
- Kalau `ssh -T` ditolak: tambahkan SSH key VPS (`cat ~/.ssh/id_ed25519.pub`, buat dulu dengan `ssh-keygen -t ed25519` kalau belum ada) sebagai **Deploy key** (read-only) di repo GitHub `syahrildev14/BMS-New` → *Settings → Deploy keys*.

### 1b. Pastikan cloudflared terhubung ke shared-network

Kalau absensi sudah jalan via tunnel, langkah ini biasanya **sudah beres**. Cek:

```bash
docker network inspect shared-network --format '{{range .Containers}}{{.Name}}{{"\n"}}{{end}}'
```

Kalau container `cloudflared` belum ada di daftar:

```bash
docker ps --format '{{.Names}}\t{{.Image}}' | grep -i cloudflared   # catat namanya
docker network inspect shared-network >/dev/null 2>&1 || docker network create shared-network
docker network connect shared-network <NAMA_CONTAINER_CLOUDFLARED>
```

🔴 Catatan durabilitas: kalau container `cloudflared` di-*recreate* (`docker compose down && up` di stack task-management), koneksi manual ini hilang dan perlu diulang — BMS **dan** absensi akan sama-sama down (error 502 dari Cloudflare). Supaya permanen, tambahkan `shared-network` (`external: true`) ke service `cloudflared` di `docker-compose.yml` task-management. `deploy.sh` akan menampilkan peringatan kalau cloudflared tidak terdeteksi di network ini.

---

## 2. Clone repository

```bash
sudo mkdir -p /opt/apps/bms
sudo chown -R $USER:$USER /opt/apps/bms
cd /opt/apps/bms
git clone git@github.com:syahrildev14/BMS-New.git .   # titik = clone ke folder ini
chmod +x deploy.sh
```

> `git clone ... .` hanya bisa ke folder **kosong**. Kalau gagal dengan `destination path '.' already exists and is not an empty directory`, cek isi folder dengan `ls -la` dan kosongkan dulu.

> 🔴 **Kalau terpaksa clone/deploy sebagai `root`:** semua file jadi milik `root:root`, sedangkan PHP di container jalan sebagai uid `HOST_UID` (default 1000) → Laravel tidak bisa menulis `storage/` → **HTTP 500 tanpa file log**. Perbaiki dengan menyerahkan folder ke uid/gid yang sama dengan `HOST_UID`/`HOST_GID` (jangan isi `HOST_UID=0`), dan izinkan git root membaca repo milik user lain:
>
> ```bash
> chown -R 1000:1000 /opt/apps/bms
> git config --global --add safe.directory /opt/apps/bms
> ```

Cek hasil clone — file berikut harus ada:

```bash
ls deploy.sh docker-compose.yml Dockerfile .env.production.example docker/nginx/default.conf
```

---

## 3. Buat & isi `.env`

### 3a. Salin template

```bash
cd /opt/apps/bms
cp .env.production.example .env
```

### 3b. Isi nilai otomatis (UID/GID, password DB, APP_KEY)

Jalankan blok ini **sekali** (copy-paste utuh). Password dan `APP_KEY` dibuat acak:

```bash
cd /opt/apps/bms
sed -i "s|^HOST_UID=.*|HOST_UID=$(id -u)|" .env
sed -i "s|^HOST_GID=.*|HOST_GID=$(id -g)|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 24)|" .env
sed -i "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$(openssl rand -hex 24)|" .env
sed -i "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" .env
```

> Password memakai `-hex` (hanya huruf a–f dan angka) supaya aman dipakai di `.env`, YAML, dan command line tanpa perlu di-escape.

### 3c. Periksa hasilnya

```bash
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|APP_KEY|APP_PORT|HOST_UID|HOST_GID|DB_HOST|DB_DATABASE|DB_USERNAME|DB_PASSWORD|DB_ROOT_PASSWORD|SESSION_DOMAIN)=' .env
```

Harus terlihat seperti ini (nilai password/key tentu berbeda):

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://bms.deevatech.my.id
APP_KEY=base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
APP_PORT=8083
HOST_UID=1000                 # = hasil `id -u`
HOST_GID=1000                 # = hasil `id -g`
DB_HOST=mysql                 # nama service di docker-compose, JANGAN 127.0.0.1
DB_DATABASE=bms
DB_USERNAME=bms
DB_PASSWORD=<acak>            # bukan lagi GANTI_PASSWORD_KUAT
DB_ROOT_PASSWORD=<acak>       # bukan lagi GANTI_ROOT_PASSWORD_KUAT
SESSION_DOMAIN=bms.deevatech.my.id
```

Sisanya (mail, dll.) boleh dibiarkan default; edit manual dengan `nano .env` jika perlu.

### 3d. Simpan salinan `.env` di tempat aman

```bash
chmod 600 .env
```

Simpan `APP_KEY` dan `DB_ROOT_PASSWORD` di password manager. **`APP_KEY` jangan pernah diganti** setelah aplikasi dipakai (session & data terenkripsi jadi tidak terbaca).

🔴 Kenapa `APP_KEY` diisi sekarang, bukan dibiarkan kosong: `.env` dimuat ke container via `env_file`, jadi nilainya menjadi environment variable container. Kalau `APP_KEY` kosong saat container dibuat, `php artisan key:generate` memang menulis ke file `.env`, tapi container tetap membawa `APP_KEY=` kosong dan Laravel tidak menimpanya → error *No application encryption key has been specified*.

🔴 `DB_PASSWORD`/`DB_ROOT_PASSWORD` dipakai sebagai `MYSQL_PASSWORD`/`MYSQL_ROOT_PASSWORD` oleh service `mysql`, dan hanya berlaku saat volume MySQL **pertama kali** dibuat. Mengganti nilainya setelah deploy pertama **tidak** mengubah password di dalam MySQL (app akan gagal konek). Jadi pastikan sudah benar sebelum langkah 4.

🔴 `HOST_UID`/`HOST_GID`: user `www-data` di dalam image di-set ke UID/GID user VPS, sehingga file di bind mount (`storage/`, `bootstrap/cache`, `vendor/`) dimiliki user Anda sendiri. Karena itu **tidak perlu** `sudo chown www-data`.

---

## 4. Build & jalankan (deploy pertama)

```bash
cd /opt/apps/bms
./deploy.sh --first
```

Proses pertama makan waktu beberapa menit (build image PHP, `composer install`, `npm ci`). Yang dilakukan script:

1. Pastikan `shared-network` ada + cek cloudflared terhubung
2. `docker compose build app`
3. Start `mysql`, tunggu sampai `healthy`, lalu start `app` + `nginx`
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build` di container `node:20` sementara
6. `key:generate` (hanya jika `APP_KEY` kosong — seharusnya tidak, karena sudah diisi di langkah 3b) + `storage:link`
7. `migrate --force`
8. Seed **hanya** `RoleAndPermissionSeeder` — data dummy tidak ikut (`DatabaseSeeder` juga menolak jalan di production)
9. `optimize` (cache config/route/view/event)
10. Start/restart `queue` + `scheduler`

Script berhenti di langkah pertama yang gagal (`set -e`). Perbaiki penyebabnya (lihat [Troubleshooting](#troubleshooting)), lalu jalankan ulang `./deploy.sh --first` — aman diulang.

> Kenapa `composer install`/`npm run build` dijalankan setelah container hidup: `vendor/` dan `public/build` datang dari bind mount host (`.:/var/www/html`), jadi hasil build di image akan tertutup mount tersebut.

### Cek lokal di VPS

```bash
docker compose ps                      # 5 container Up; bms-mysql (healthy)
curl -I http://127.0.0.1:8083/up       # harus HTTP/1.1 200 OK
docker compose exec app php artisan about --only=environment   # Environment: production, Debug Mode: OFF
```

Kalau `curl` sudah 200, aplikasi siap — lanjut sambungkan ke Cloudflare.

---

## 5. Cloudflare Zero Trust Dashboard — tambah route, TANPA halaman login

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

## 6. Buat akun admin pertama

Akun dummy dari seeder tidak ada di produksi, jadi buat manual. Ganti email & password lalu jalankan:

```bash
cd /opt/apps/bms
docker compose exec -e HOME=/tmp app php artisan tinker --execute='$u = App\Models\User::firstOrCreate(["email" => "admin@deevatech.my.id"], ["name" => "Administrator", "password" => bcrypt("PASSWORD_KUAT_ANDA")]); $u->assignRole("admin"); echo "OK: " . $u->email . PHP_EOL;'
```

> Perintah sengaja dibuat **satu baris** dengan kutip tunggal `'...'` di luar: copy-paste multi-baris ke terminal SSH sering kehilangan baris penutup `"` sehingga bash menunggu input (`>`). Aman diulang — `firstOrCreate` tidak membuat user ganda (kalau user sudah ada, password-nya tidak diubah).

Harus keluar `OK: admin@deevatech.my.id`. Login di https://bms.deevatech.my.id, lalu input data master (satuan, supplier, dapur, gudang, item) lewat aplikasi.

> `-e HOME=/tmp` wajib: tinker (psysh) menulis config ke `$HOME/.config/psysh`, sedangkan home `www-data` (`/var/www`) milik root dan tidak bisa ditulis → error `Writing to directory /var/www/.config/psysh is not allowed`.
>
> Error `There is no role named admin` → seeder role belum jalan: `docker compose exec app php artisan db:seed --class=RoleAndPermissionSeeder --force`, lalu ulangi.

---

## 7. Checklist verifikasi

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

## 8. Update kode saat deploy berikutnya

```bash
cd /opt/apps/bms
./deploy.sh          # TANPA --first
```

Script menjalankan `git pull --ff-only`, build ulang image, composer, build asset, migrate, cache ulang, dan restart `app` + `queue` + `scheduler`. **Tidak** menjalankan seeder. Tidak ada yang perlu diubah di sisi Cloudflare.

> `git pull --ff-only` gagal kalau ada file yang diubah langsung di server. Cek dengan `git status`; jangan edit kode di VPS — ubah di lokal, push, lalu deploy.

Jika ada permission baru di `RoleAndPermissionSeeder` (aman dijalankan berulang):

```bash
docker compose exec app php artisan db:seed --class=RoleAndPermissionSeeder --force
docker compose exec app php artisan permission:cache-reset
```

Jika mengubah `.env` (selain password DB), container harus **dibuat ulang** — `restart` saja tidak cukup karena `env_file` hanya dibaca saat container dibuat:

```bash
docker compose up -d --force-recreate app queue scheduler
docker compose exec app php artisan optimize
```

---

## 9. Backup database

Backup dari menu aplikasi tersimpan di `/opt/apps/bms/storage/app/private/backups/` — masih di VPS yang sama, jadi salin berkala ke tempat lain (`scp`/`rsync` ke lokal atau cloud storage).

Backup manual:

```bash
cd /opt/apps/bms
source <(grep -E '^DB_(DATABASE|ROOT_PASSWORD)=' .env)
docker compose exec -T mysql mysqldump -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE" | gzip > ~/bms_$(date +%F).sql.gz
```

Restore:

```bash
cd /opt/apps/bms
source <(grep -E '^DB_(DATABASE|ROOT_PASSWORD)=' .env)
gunzip -c ~/bms_YYYY-MM-DD.sql.gz | docker compose exec -T mysql mysql -uroot -p"$DB_ROOT_PASSWORD" "$DB_DATABASE"
```

---

## Perintah yang sering dipakai

Semua dijalankan dari `/opt/apps/bms`:

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
| `File .env belum ada` saat `./deploy.sh` | Langkah 3 belum dikerjakan: `cp .env.production.example .env` lalu isi |
| `permission denied while trying to connect to the Docker daemon socket` | User belum di grup docker: `sudo usermod -aG docker $USER`, logout & login ulang |
| `Permission denied (publickey)` saat clone / `git pull` | SSH key VPS belum jadi Deploy key di GitHub — lihat langkah 1a |
| `./deploy.sh: Permission denied` | `chmod +x deploy.sh` |
| Script berhenti di "menunggu mysql sehat..." terus | `docker compose logs mysql`. Kalau password salah karena volume lama: volume dibuat dengan password sebelumnya. **Hanya jika belum ada data penting**: `docker compose down && docker volume rm bms_bms_mysql_data` lalu deploy ulang |
| `SQLSTATE[HY000] [1045] Access denied for user 'bms'` | `DB_PASSWORD` di `.env` diganti setelah volume MySQL dibuat — kembalikan nilai lama, atau lihat baris di atas |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL belum siap / `DB_HOST` bukan `mysql` — `docker compose ps mysql` |
| `No application encryption key has been specified` | `APP_KEY` kosong saat container dibuat. Isi `APP_KEY` (langkah 3b), lalu `docker compose up -d --force-recreate app queue scheduler && docker compose exec app php artisan optimize` |
| Cloudflare error **502 / Bad gateway** | cloudflared tidak bisa menjangkau `bms-nginx`. Cek langkah 1b (`docker network connect ...`) dan `docker compose ps nginx` |
| Cloudflare error **1033** | Tunnel task-management mati — cek container cloudflared |
| Muncul halaman login Cloudflare | Ada Access Application (wildcard) yang mencakup `bms.deevatech.my.id` — lihat langkah 5 |
| `network shared-network declared as external, but could not be found` | `docker network create shared-network`, lalu ulangi langkah 1b |
| HTTP 500 di `/up` dan `storage/logs/` kosong | Folder project milik `root` (clone sebagai root) — lihat catatan root di langkah 2: `chown -R 1000:1000 .` |
| `Permission denied` di `storage/` / `bootstrap/cache` | `HOST_UID/HOST_GID` di `.env` salah. Perbaiki → `docker compose build app && docker compose up -d --force-recreate app queue scheduler` |
| CSS/JS tidak muncul / mixed content / redirect loop | Pastikan `APP_URL=https://bms.deevatech.my.id`, lalu `docker compose up -d --force-recreate app queue scheduler && docker compose exec app php artisan optimize` |
| `/build/manifest.json` not found | Build Vite gagal — ulangi `./deploy.sh`, perhatikan tahap "Build asset frontend" |
| PDF tidak pernah terbuat | `docker compose logs queue`; job gagal: `docker compose exec app php artisan queue:failed` |
| Perubahan `.env` tidak berefek | `env_file` hanya dibaca saat container dibuat: `docker compose up -d --force-recreate app queue scheduler && docker compose exec app php artisan optimize` |
| Login berhasil tapi langsung ter-logout / error 419 | `SESSION_DOMAIN` atau `SESSION_SECURE_COOKIE` tidak cocok — harus `bms.deevatech.my.id` dan `true`, akses lewat `https://`, bukan `http://127.0.0.1:8083` |
