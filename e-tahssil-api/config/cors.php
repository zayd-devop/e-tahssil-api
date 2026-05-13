<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // On met l'URL EXACTE de ton frontend (SANS le slash / à la fin)
    'allowed_origins' => ['https://e-tahssil.vercel.app', 'http://localhost:3000', 'http://localhost:5173'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // OBLIGATOIRE POUR SANCTUM ET AXIOS
    'supports_credentials' => true,
];
