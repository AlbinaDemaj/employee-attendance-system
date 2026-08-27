#!/usr/bin/env sh
#
# Nisja e API-se ne Render. Ekzekutohet sa here qe ngrihet kontejneri.
set -eu

cd /app

PORT="${PORT:-8080}"

echo "==> Prezenca API - nisja (mjedisi: ${APP_ENV:-production})"

# ---------------------------------------------------------------------------
# 1. Dosjet e cache-it
# ---------------------------------------------------------------------------
# Disku i Render-it eshte i perkohshem: cdo rinisje e kthen kontejnerin te
# imazhi fillestar, prandaj keto dosje krijohen sa here, jo vetem njehere.
mkdir -p     storage/app/public     storage/framework/cache/data     storage/framework/sessions     storage/framework/views     storage/logs     bootstrap/cache

# ---------------------------------------------------------------------------
# 2. Lejet
# ---------------------------------------------------------------------------
chmod -R ug+rwX storage bootstrap/cache

# ---------------------------------------------------------------------------
# 3. APP_KEY
# ---------------------------------------------------------------------------
# Nuk e gjenerojme automatikisht: nje celes i ri ne cdo rinisje do t'i bente te
# palexueshme te dhenat e enkriptuara dhe do t'i shkeputte sesionet. Me mire
# ndalemi ketu me nje mesazh te qarte.
if [ -z "${APP_KEY:-}" ]; then
    echo "GABIM: APP_KEY nuk eshte vendosur." >&2
    echo "Gjenerohet lokalisht me:  php artisan key:generate --show" >&2
    echo "dhe vendoset te Render > Environment si APP_KEY." >&2
    exit 1
fi

# ---------------------------------------------------------------------------
# 4. Pastrimi i konfigurimit
# ---------------------------------------------------------------------------
php artisan config:clear

# ---------------------------------------------------------------------------
# 5. Migrimet
# ---------------------------------------------------------------------------
echo "==> Migrimet"
php artisan migrate --force

# ---------------------------------------------------------------------------
# 6. Te dhenat demo (vetem me kerkese)
# ---------------------------------------------------------------------------
if [ "${RUN_SEEDER:-false}" = "true" ]; then
    echo "==> RUN_SEEDER=true - po mbushen te dhenat demo"
    php artisan db:seed --force
else
    echo "==> Seed-i u anashkalua (RUN_SEEDER nuk eshte 'true')"
fi

# ---------------------------------------------------------------------------
# 7. Lidhja e storage-it
# ---------------------------------------------------------------------------
# Deshton pa demtuar asgje nese lidhja ekziston tashme.
php artisan storage:link 2>/dev/null || true

# ---------------------------------------------------------------------------
# 8. Serveri
# ---------------------------------------------------------------------------
# Shenim: `artisan serve` eshte serveri i integruar i PHP-se. Mjafton per nje
# instance te vetme ne planin Free; per ngarkese reale duhet nginx + php-fpm
# ose FrankenPHP. PHP_CLI_SERVER_WORKERS lejon disa kerkesa paralele.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

echo "==> Po degjohet ne 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="${PORT}" --no-reload
