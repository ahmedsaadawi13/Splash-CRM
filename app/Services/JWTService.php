<?php

/**
 * JWT Service
 *
 * File: app/Services/JWTService.php
 *
 * Handles JSON Web Token (JWT) operations:
 * - Generate access tokens (short-lived)
 * - Generate refresh tokens (long-lived)
 * - Validate and decode tokens
 * - Extract payload from tokens
 * - Check token expiration
 *
 * @package App\Services
 * @version 1.0.0
 */

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTService
{
    private $secret;
    private $algo;
    private $accessTtl;
    private $refreshTtl;
    private $issuer;

    public function __construct()
    {
        $this->secret = config('jwt.secret');
        $this->algo = config('jwt.algo', 'HS256');
        $this->accessTtl = config('jwt.access_ttl', 3600);
        $this->refreshTtl = config('jwt.refresh_ttl', 604800);
        $this->issuer = config('jwt.issuer');
    }

    /**
     * Generate access token
     */
    public function generateAccessToken(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->accessTtl;

        $data = [
            'iat' => $issuedAt,
            'iss' => $this->issuer,
            'exp' => $expire,
            'data' => $payload
        ];

        return JWT::encode($data, $this->secret, $this->algo);
    }

    /**
     * Generate refresh token
     */
    public function generateRefreshToken(array $payload): string
    {
        $issuedAt = time();
        $expire = $issuedAt + $this->refreshTtl;

        $data = [
            'iat' => $issuedAt,
            'iss' => $this->issuer,
            'exp' => $expire,
            'type' => 'refresh',
            'data' => $payload
        ];

        return JWT::encode($data, $this->secret, $this->algo);
    }

    /**
     * Validate and decode token
     */
    public function validateToken(string $token): ?object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algo));
            return $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Extract payload from token
     */
    public function getPayload(string $token): ?array
    {
        $decoded = $this->validateToken($token);

        if (!$decoded || !isset($decoded->data)) {
            return null;
        }

        return (array) $decoded->data;
    }

    /**
     * Check if token is expired
     */
    public function isExpired(string $token): bool
    {
        $decoded = $this->validateToken($token);

        if (!$decoded) {
            return true;
        }

        return $decoded->exp < time();
    }
}
