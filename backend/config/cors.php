<?php

/*
 | Frontend-i xhiron gjithmonë në origjinë tjetër nga API-ja, prandaj duhet
 | lejuar shprehimisht.
 |
 | Në prodhim (Render) origjina vjen nga `FRONTEND_URL`. Aty mund të vendosen
 | edhe disa adresa të ndara me presje, p.sh. domeni i Render-it bashkë me një
 | domen tënd:
 |
 |   FRONTEND_URL=https://prezenca.onrender.com,https://prezenca.com
 |
 | Origjinat lokale shtohen vetëm jashtë prodhimit, që serveri publik të mos
 | lejojë kurrë `localhost` — me `supports_credentials` të ndezur kjo do të
 | ishte hapje e panevojshme.
 */

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173'))
)));

$isProduction = env('APP_ENV') === 'production';

if (! $isProduction) {
    $origins = array_merge($origins, [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
    ]);
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique($origins)),

    'allowed_origins_patterns' => $isProduction ? [] : [
        // çdo port i localhost-it, që një port i zhvendosur i Vite-s të punojë
        '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
