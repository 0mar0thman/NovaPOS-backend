<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'login',
        'logout',
        'register',
        'user',
        'forgot-password',
        'reset-password',
        'email/verification-notification',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://pos-nova.up.railway.app',
        'http://localhost:8080',
        'http://127.0.0.1:8080',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];

