# Prezenca — Backend (Laravel 12)

API REST me Laravel 12 + Laravel Sanctum për sistemin e prezencës.

Udhëzimet e plota të instalimit, konfigurimit dhe nisjes ndodhen te
[README-ja kryesore e projektit](../README.md).

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

Testet: `php artisan test`
