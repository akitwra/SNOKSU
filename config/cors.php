<?php

return [
    'paths' => ['api/questions'],
    'allowed_methods' => ['POST', 'OPTIONS'],
    'allowed_origins' => ['https://akitwra.github.io'],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
