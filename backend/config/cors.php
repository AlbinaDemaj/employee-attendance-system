<?php

/*
 | The React dev server runs on its own origin, so the API has to allow it.
 | Both 127.0.0.1 and localhost are listed because browsers treat them as two
 | different origins (and Edge likes to upgrade "localhost" to https).
 */

$frontend = env('FRONTEND_URL', 'http://localhost:5173');

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique(array_filter([
        $frontend,
        'http://localhost:5173',
        'http://127.0.0.1:5173',
        'http://localhost:4173',
        'http://127.0.0.1:4173',
    ]))),

    'allowed_origins_patterns' => [
        // any localhost / 127.0.0.1 port, so a shifted Vite port still works
        '#^http://(localhost|127\.0\.0\.1)(:\d+)?$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
