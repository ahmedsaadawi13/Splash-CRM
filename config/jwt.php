<?php

return [
    'secret' => env('JWT_SECRET', ''),
    'algo' => env('JWT_ALGO', 'HS256'),
    'access_ttl' => env('JWT_ACCESS_TTL', 3600), // 1 hour
    'refresh_ttl' => env('JWT_REFRESH_TTL', 604800), // 7 days
    'issuer' => env('APP_URL', 'http://localhost'),
    'audience' => env('APP_URL', 'http://localhost'),
];
