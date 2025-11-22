<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Web Routes
 *
 * Routes for serving the frontend application
 */

// Serve frontend SPA
$app->get('/', function (Request $request, Response $response) {
    $html = file_get_contents(__DIR__ . '/../public/index.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});

// Catch-all route for SPA routing
$app->get('/{routes:.+}', function (Request $request, Response $response) {
    $path = $request->getUri()->getPath();

    // If requesting API, return 404
    if (strpos($path, '/api/') === 0) {
        $response->getBody()->write(json_encode([
            'error' => 'Endpoint not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    // Otherwise serve SPA
    $html = file_get_contents(__DIR__ . '/../public/index.html');
    $response->getBody()->write($html);
    return $response->withHeader('Content-Type', 'text/html');
});
