# syntax=docker/dockerfile:1
#
# Imazh prodhimi per API-ne e Prezences (Laravel 12).
# I ndertuar per Render Free: nje proces i vetem, pa disk te perhershem,
# me databaze te jashtme PostgreSQL (Supabase).

FROM php:8.3-cli-bookworm

# ---------------------------------------------------------------------------
# 1. Ekstensionet e PHP-se
# ---------------------------------------------------------------------------
# Ne imazhin zyrtar te PHP-se keto vijne tashme te kompiluara: openssl,
# tokenizer, ctype, fileinfo, curl, mbstring, xml. Nga burimi na duhet vetem
# drejtuesi i PostgreSQL-it (dhe opcache per shpejtesi).
#
# Hapi i fundit e ndal ndertimin nese ndonje ekstension i kerkuar mungon, qe
# nje imazh i mangut te mos arrije kurre ne Render.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        unzip \
        git \
        ca-certificates; \
    docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql opcache zip; \
    apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
    rm -rf /var/lib/apt/lists/*; \
    php -r '$need = ["pdo_pgsql","mbstring","openssl","tokenizer","xml","ctype","fileinfo","curl"]; \
            $missing = array_values(array_filter($need, fn ($e) => ! extension_loaded($e))); \
            if ($missing) { fwrite(STDERR, "Mungojne ekstensionet: ".implode(", ", $missing)."\n"); exit(1); } \
            echo "Te gjitha ekstensionet e kerkuara jane te pranishme.\n";'

# Cilesime PHP per prodhim.
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.enable_cli=0"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.validate_timestamps=0"; \
        echo "memory_limit=256M"; \
        echo "upload_max_filesize=8M"; \
        echo "post_max_size=8M"; \
        echo "expose_php=Off"; \
    } > /usr/local/etc/php/conf.d/zz-app.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# ---------------------------------------------------------------------------
# 2. Varesite
# ---------------------------------------------------------------------------
# Fillimisht vetem manifestet, qe shtresa e varesive te ri-perdoret nga cache-i
# sa here qe ndryshon vetem kodi. `--no-scripts` sepse artisan-i ende s'eshte
# kopjuar ne kete pike.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-interaction \
        --no-scripts \
        --prefer-dist

# ---------------------------------------------------------------------------
# 3. Kodi i aplikacionit
# ---------------------------------------------------------------------------
COPY . .

RUN composer dump-autoload --optimize --no-dev --no-interaction \
 && mkdir -p storage/framework/cache/data \
             storage/framework/sessions \
             storage/framework/views \
             storage/logs \
             bootstrap/cache \
 && chmod -R ug+rwX storage bootstrap/cache \
 && chmod +x docker/start.sh

# Render e cakton vete $PORT; kjo vlere eshte thjesht parazgjedhje lokale.
ENV PORT=8080 \
    APP_ENV=production \
    APP_DEBUG=false

EXPOSE 8080

CMD ["/app/docker/start.sh"]
