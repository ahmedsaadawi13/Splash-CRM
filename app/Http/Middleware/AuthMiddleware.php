<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JWTService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    private $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Get authorization header
        $authHeader = $request->getHeaderLine('Authorization');

        if (empty($authHeader)) {
            return $this->unauthorizedResponse('Missing authorization token');
        }

        // Extract token
        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Invalid authorization header format');
        }

        $token = $matches[1];

        // Validate token
        $payload = $this->jwtService->getPayload($token);

        if (!$payload) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }

        // Load user
        $user = User::find($payload['user_id']);

        if (!$user) {
            return $this->unauthorizedResponse('User not found');
        }

        if ($user->status !== 'active') {
            return $this->unauthorizedResponse('User account is not active');
        }

        // Set current tenant context
        $_ENV['CURRENT_TENANT_ID'] = $user->tenant_id;

        // Add user to request attributes
        $request = $request->withAttribute('user', $user);
        $request = $request->withAttribute('tenant_id', $user->tenant_id);

        return $handler->handle($request);
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
