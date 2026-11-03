#!/usr/bin/env bash
# Deploy / update BMS di VPS (Docker + Cloudflare Tunnel existing).
# Lokasi project: /opt/apps/bms
#   Pertama kali : ./deploy.sh --first
#   Update biasa : ./deploy.sh
set -euo pipefail

cd "$(dirname "$0")"

FIRST_RUN=false
[[ "${1:-}" == "--first" ]] && FIRST_RUN=true

if [[ ! -f .env ]]; then
    echo "File .env belum ada. Salin dulu: cp .env.production.example .env lalu isi nilainya."
    exit 1
fi

# shared-network dipakai bersama cloudflared (stack task-management)
if ! docker network inspect shared-network >/dev/null 2>&1; then
    echo ">> Membuat network shared-network"
    docker network create shared-network
fi

if ! docker network inspect shared-network --format '{{range .Containers}}{{.Name}} {{end}}' | grep -qi cloudflared; then
    echo "!! PERINGATAN: container cloudflared belum terhubung ke shared-network."
    echo "   Jalankan: docker network connect shared-network <NAMA_CONTAINER_CLOUDFLARED>"
    echo "   (lihat docs/DEPLOY.md langkah 0). Deploy tetap dilanjutkan."
fi

if [[ "$FIRST_RUN" == false ]]; then
    echo ">> git pull"
    git pull --ff-only
fi

echo ">> Build image"
docker compose build app

echo ">> Start mysql & tunggu sampai healthy"
docker compose up -d mysql
until docker inspect -f '{{.State.Health.Status}}' bms-mysql 2>/dev/null | grep -q healthy; do
    echo "   menunggu mysql sehat..."; sleep 3
done

echo ">> Start app & nginx"
docker compose up -d app nginx

echo ">> Composer install (tanpa dev)"
docker compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction

echo ">> Build asset frontend (Vite)"
docker run --rm \
    -u "$(id -u):$(id -g)" \
    -e HOME=/tmp \
    -v "$(pwd)":/var/www/html -w /var/www/html \
    node:20 sh -c "npm ci --no-audit --no-fund && npm run build"

if [[ "$FIRST_RUN" == true ]]; then
    if ! grep -q '^APP_KEY=base64:' .env; then
        echo ">> Generate APP_KEY"
        docker compose exec -T app php artisan key:generate --force
    fi
    docker compose exec -T app php artisan storage:link || true
fi

echo ">> Migrasi database"
docker compose exec -T app php artisan migrate --force

if [[ "$FIRST_RUN" == true ]]; then
    # Hanya role & permission. DatabaseSeeder penuh berisi data dummy dan
    # akun admin dengan password lemah, jadi JANGAN dijalankan di produksi.
    echo ">> Seeder role & permission"
    docker compose exec -T app php artisan db:seed --class=RoleAndPermissionSeeder --force
fi

echo ">> Cache config / route / view"
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize

echo ">> Start / restart queue & scheduler agar memuat kode baru"
docker compose exec -T app php artisan queue:restart
docker compose up -d queue scheduler
docker compose restart app queue scheduler

echo ">> Selesai."
docker compose ps
echo "Cek: https://bms.deevatech.my.id"
