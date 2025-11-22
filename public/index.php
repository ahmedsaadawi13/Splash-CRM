<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Bootstrap the application
$app = require __DIR__ . '/../bootstrap/app.php';

// Load routes
require __DIR__ . '/../routes/api.php';
require __DIR__ . '/../routes/web.php';

// Health check endpoint
$app->get('/health', function (Request $request, Response $response) {
    $data = [
        'status' => 'ok',
        'timestamp' => time(),
        'version' => '1.0.0',
    ];

    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
});

// Run the application
$app->run();
