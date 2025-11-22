<?php

/**
 * Authentication Controller
 *
 * File: app/Http/Controllers/AuthController.php
 *
 * Handles all authentication operations including:
 * - User login with JWT token generation
 * - User registration with tenant creation
 * - Token refresh mechanism
 * - User logout
 * - Password reset flow
 * - Get current authenticated user
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\JWTService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;
use Exception;

class AuthController extends BaseController
{
    private $jwtService;

    public function __construct(JWTService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    /**
     * Login
     *
     * POST /api/v1/auth/login
     */
    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validate input
        if (empty($data['email']) || empty($data['password'])) {
            return $this->validationError($response, [
                'email' => 'Email is required',
                'password' => 'Password is required',
            ]);
        }

        try {
            // Find user
            $user = User::where('email', $data['email'])->first();

            if (!$user || !$user->verifyPassword($data['password'])) {
                return $this->unauthorized($response, 'Invalid credentials');
            }

            // Check user status
            if ($user->status !== 'active') {
                return $this->forbidden($response, 'Account is not active');
            }

            // Check tenant status
            if (!$user->tenant->isActive()) {
                return $this->forbidden($response, 'Tenant account is not active');
            }

            // Update last login
            $user->updateLastLogin($this->getClientIp($request));

            // Generate tokens
            $payload = [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'email' => $user->email,
            ];

            $accessToken = $this->jwtService->generateAccessToken($payload);
            $refreshToken = $this->jwtService->generateRefreshToken($payload);

            // Store refresh token
            DB::table('refresh_tokens')->insert([
                'user_id' => $user->id,
                'token' => hash('sha256', $refreshToken),
                'expires_at' => date('Y-m-d H:i:s', time() + config('jwt.refresh_ttl')),
                'user_agent' => $request->getHeaderLine('User-Agent'),
                'ip_address' => $this->getClientIp($request),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            return $this->success($response, [
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
                'expires_in' => config('jwt.access_ttl'),
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                ],
            ], 'Login successful');

        } catch (Exception $e) {
            $this->logError('Login error: ' . $e->getMessage());
            return $this->error($response, 'Login failed', 500);
        }
    }

    /**
     * Register new user
     *
     * POST /api/v1/auth/register
     */
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        // Validate input
        $errors = [];
        if (empty($data['first_name'])) $errors['first_name'] = 'First name is required';
        if (empty($data['last_name'])) $errors['last_name'] = 'Last name is required';
        if (empty($data['email'])) $errors['email'] = 'Email is required';
        if (empty($data['password'])) $errors['password'] = 'Password is required';
        if (empty($data['tenant_name'])) $errors['tenant_name'] = 'Company name is required';

        if (!empty($errors)) {
            return $this->validationError($response, $errors);
        }

        // Validate email format
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->validationError($response, ['email' => 'Invalid email format']);
        }

        // Validate password strength
        if (strlen($data['password']) < 8) {
            return $this->validationError($response, ['password' => 'Password must be at least 8 characters']);
        }

        try {
            DB::beginTransaction();

            // Check if email already exists
            if (User::where('email', $data['email'])->exists()) {
                DB::rollBack();
                return $this->validationError($response, ['email' => 'Email already registered']);
            }

            // Create tenant
            $tenant = new \App\Models\Tenant();
            $tenant->uuid = uuid();
            $tenant->name = $data['tenant_name'];
            $tenant->slug = strtolower(str_replace(' ', '-', $data['tenant_name'])) . '-' . substr(uuid(), 0, 8);
            $tenant->status = 'trial';
            $tenant->trial_ends_at = date('Y-m-d H:i:s', strtotime('+30 days'));
            $tenant->save();

            // Create user
            $user = new User();
            $user->uuid = uuid();
            $user->tenant_id = $tenant->id;
            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            $user->email = $data['email'];
            $user->setPassword($data['password']);
            $user->status = 'active';
            $user->save();

            DB::commit();

            return $this->success($response, [
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ],
            ], 'Registration successful', 201);

        } catch (Exception $e) {
            DB::rollBack();
            $this->logError('Registration error: ' . $e->getMessage());
            return $this->error($response, 'Registration failed', 500);
        }
    }

    /**
     * Get current user
     *
     * GET /api/v1/auth/me
     */
    public function me(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        if (!$user) {
            return $this->unauthorized($response);
        }

        // Load relationships
        $user->load('roles', 'tenant');

        return $this->success($response, [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'locale' => $user->locale,
            'timezone' => $user->timezone,
            'status' => $user->status,
            'tenant' => [
                'id' => $user->tenant->id,
                'name' => $user->tenant->name,
                'slug' => $user->tenant->slug,
            ],
            'roles' => $user->roles->map(function ($role) {
                return ['id' => $role->id, 'name' => $role->name, 'slug' => $role->slug];
            }),
        ]);
    }

    /**
     * Refresh token
     *
     * POST /api/v1/auth/refresh
     */
    public function refresh(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['refresh_token'])) {
            return $this->validationError($response, ['refresh_token' => 'Refresh token is required']);
        }

        $refreshToken = $data['refresh_token'];

        // Validate refresh token
        $payload = $this->jwtService->getPayload($refreshToken);

        if (!$payload) {
            return $this->unauthorized($response, 'Invalid refresh token');
        }

        // Check if token exists and not revoked
        $tokenHash = hash('sha256', $refreshToken);
        $storedToken = DB::table('refresh_tokens')
            ->where('token', $tokenHash)
            ->where('revoked', false)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$storedToken) {
            return $this->unauthorized($response, 'Refresh token not found or expired');
        }

        // Generate new access token
        $accessToken = $this->jwtService->generateAccessToken($payload);

        return $this->success($response, [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.access_ttl'),
        ], 'Token refreshed');
    }

    /**
     * Logout
     *
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (!empty($data['refresh_token'])) {
            $tokenHash = hash('sha256', $data['refresh_token']);

            DB::table('refresh_tokens')
                ->where('token', $tokenHash)
                ->update([
                    'revoked' => true,
                    'revoked_at' => date('Y-m-d H:i:s'),
                ]);
        }

        return $this->success($response, null, 'Logout successful');
    }

    /**
     * Forgot password
     *
     * POST /api/v1/auth/forgot-password
     */
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['email'])) {
            return $this->validationError($response, ['email' => 'Email is required']);
        }

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));

            DB::table('password_resets')->insert([
                'email' => $data['email'],
                'token' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // TODO: Send email with reset link
            // For now, return token in response (remove in production)
            return $this->success($response, [
                'reset_token' => $token, // Remove this in production
            ], 'Password reset email sent');
        }

        // Don't reveal if user exists
        return $this->success($response, null, 'If email exists, password reset link will be sent');
    }

    /**
     * Reset password
     *
     * POST /api/v1/auth/reset-password
     */
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();

        if (empty($data['token']) || empty($data['password'])) {
            return $this->validationError($response, [
                'token' => 'Reset token is required',
                'password' => 'New password is required',
            ]);
        }

        $tokenHash = hash('sha256', $data['token']);

        $reset = DB::table('password_resets')
            ->where('token', $tokenHash)
            ->where('used', false)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->first();

        if (!$reset) {
            return $this->error($response, 'Invalid or expired reset token', 400);
        }

        $user = User::where('email', $reset->email)->first();

        if (!$user) {
            return $this->error($response, 'User not found', 404);
        }

        $user->setPassword($data['password']);
        $user->save();

        // Mark token as used
        DB::table('password_resets')
            ->where('token', $tokenHash)
            ->update(['used' => true]);

        return $this->success($response, null, 'Password reset successful');
    }

    /**
     * Get client IP address
     */
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();

        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return $serverParams['HTTP_X_FORWARDED_FOR'];
        }

        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}
