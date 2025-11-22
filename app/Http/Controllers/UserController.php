<?php

/**
 * User Controller
 *
 * File: app/Http/Controllers/UserController.php
 *
 * Handles all user management operations including:
 * - List users with pagination, search, and filtering
 * - Create new users with role assignment
 * - Read, update, delete user accounts
 * - Manage user roles and permissions
 * - Update user settings and preferences
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class UserController extends BaseController
{
    private $validator;
    private $audit;

    public function __construct(ValidationService $validator, AuditService $audit)
    {
        $this->validator = $validator;
        $this->audit = $audit;
    }

    /**
     * List all users
     * GET /api/v1/users
     */
    public function index(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $user = $request->getAttribute('user');

        $page = max(1, (int)($params['page'] ?? 1));
        $perPage = min(100, max(1, (int)($params['per_page'] ?? 20)));

        $query = User::query()->where('tenant_id', $user->tenant_id);

        // Search
        if (!empty($params['search'])) {
            $search = $params['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Filters
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // Sorting
        $sortField = $params['sort'] ?? 'created_at';
        $sortOrder = strtoupper($params['order'] ?? 'DESC');

        if (in_array($sortField, ['id', 'first_name', 'last_name', 'email', 'created_at']) && in_array($sortOrder, ['ASC', 'DESC'])) {
            $query->orderBy($sortField, $sortOrder);
        }

        $total = $query->count();

        $users = $query
            ->with('roles:id,name,slug')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($user) {
                // Hide sensitive data
                unset($user->password_hash);
                return $user;
            });

        return $this->success($response, [
            'users' => $users,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Create a new user
     * POST /api/v1/users
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $rules = [
            'first_name' => 'required|max:100',
            'last_name' => 'required|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'status' => 'in:active,inactive,suspended',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $user = new User();
            $user->tenant_id = $currentUser->tenant_id;
            $user->uuid = uuid();
            $user->first_name = $data['first_name'];
            $user->last_name = $data['last_name'];
            $user->email = $data['email'];
            $user->setPassword($data['password']);
            $user->phone = $data['phone'] ?? null;
            $user->locale = $data['locale'] ?? 'en';
            $user->timezone = $data['timezone'] ?? 'UTC';
            $user->status = $data['status'] ?? 'active';
            $user->save();

            // Assign roles if provided
            if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
                foreach ($data['role_ids'] as $roleId) {
                    DB::table('role_user')->insert([
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->audit->log(
                'user',
                $user->id,
                'created',
                null,
                $user->toArray(),
                $currentUser->id,
                $currentUser->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            $user->load('roles');
            unset($user->password_hash);

            return $this->success($response, $user, 'User created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('User creation failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to create user', 500);
        }
    }

    /**
     * Get a specific user
     * GET /api/v1/users/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $currentUser = $request->getAttribute('user');
        $id = $args['id'];

        $user = User::where('tenant_id', $currentUser->tenant_id)
            ->with(['roles:id,name,slug', 'tenant:id,name'])
            ->find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        unset($user->password_hash);

        return $this->success($response, $user);
    }

    /**
     * Update a user
     * PUT /api/v1/users/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');
        $id = $args['id'];

        $user = User::where('tenant_id', $currentUser->tenant_id)->find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        $rules = [
            'first_name' => 'max:100',
            'last_name' => 'max:100',
            'email' => "email|unique:users,email,{$id}",
            'password' => 'min:8',
            'status' => 'in:active,inactive,suspended',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $user->toArray();

            if (isset($data['first_name'])) $user->first_name = $data['first_name'];
            if (isset($data['last_name'])) $user->last_name = $data['last_name'];
            if (isset($data['email'])) $user->email = $data['email'];
            if (isset($data['phone'])) $user->phone = $data['phone'];
            if (isset($data['locale'])) $user->locale = $data['locale'];
            if (isset($data['timezone'])) $user->timezone = $data['timezone'];
            if (isset($data['status'])) $user->status = $data['status'];
            if (isset($data['settings'])) $user->settings = $data['settings'];

            // Update password if provided
            if (!empty($data['password'])) {
                $user->setPassword($data['password']);
            }

            $user->save();

            // Update roles if provided
            if (isset($data['role_ids']) && is_array($data['role_ids'])) {
                DB::table('role_user')->where('user_id', $user->id)->delete();

                foreach ($data['role_ids'] as $roleId) {
                    DB::table('role_user')->insert([
                        'user_id' => $user->id,
                        'role_id' => $roleId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->audit->log(
                'user',
                $user->id,
                'updated',
                $oldValues,
                $user->toArray(),
                $currentUser->id,
                $currentUser->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            $user->load('roles');
            unset($user->password_hash);

            return $this->success($response, $user, 'User updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('User update failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to update user', 500);
        }
    }

    /**
     * Delete a user
     * DELETE /api/v1/users/{id}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $currentUser = $request->getAttribute('user');
        $id = $args['id'];

        // Prevent self-deletion
        if ($currentUser->id == $id) {
            return $this->error($response, 'Cannot delete your own account', 400);
        }

        $user = User::where('tenant_id', $currentUser->tenant_id)->find($id);

        if (!$user) {
            return $this->notFound($response, 'User not found');
        }

        try {
            DB::beginTransaction();

            $userData = $user->toArray();
            $user->delete();

            $this->audit->log(
                'user',
                $user->id,
                'deleted',
                $userData,
                null,
                $currentUser->id,
                $currentUser->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'User deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('User deletion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to delete user', 500);
        }
    }
}
