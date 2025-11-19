<?php

namespace App\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;

abstract class BaseController
{
    protected $logger;

    public function __construct(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Return JSON response
     */
    protected function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * Return success response
     */
    protected function success(Response $response, $data = null, string $message = 'Success', int $status = 200): Response
    {
        return $this->json($response, [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * Return error response
     */
    protected function error(Response $response, string $message, int $status = 400, array $errors = []): Response
    {
        return $this->json($response, [
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    /**
     * Return validation error response
     */
    protected function validationError(Response $response, array $errors): Response
    {
        return $this->error($response, 'Validation failed', 422, $errors);
    }

    /**
     * Return not found response
     */
    protected function notFound(Response $response, string $message = 'Resource not found'): Response
    {
        return $this->error($response, $message, 404);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorized(Response $response, string $message = 'Unauthorized'): Response
    {
        return $this->error($response, $message, 401);
    }

    /**
     * Return forbidden response
     */
    protected function forbidden(Response $response, string $message = 'Forbidden'): Response
    {
        return $this->error($response, $message, 403);
    }

    /**
     * Log info
     */
    protected function log(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info($message, $context);
        }
    }

    /**
     * Log error
     */
    protected function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error($message, $context);
        }
    }
}
