<?php

/**
 * Role Controller
 *
 * File: app/Http/Controllers/RoleController.php
 *
 * Handles role and permission management including:
 * - List roles with permissions
 * - Create new roles with permission assignment
 * - Update roles and their permissions
 * - Delete roles
 * - Assign/revoke permissions to roles
 * - Audit logging for all operations
 *
 * @package App\Http\Controllers
 * @version 1.0.0
 */

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Services\ValidationService;
use App\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Illuminate\Database\Capsule\Manager as DB;

class RoleController extends BaseController
{
    private $validator;
    private $audit;

    public function __construct(ValidationService $validator, AuditService $audit)
    {
        $this->validator = $validator;
        $this->audit = $audit;
    }

    /**
     * List all roles
     * GET /api/v1/roles
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $request->getAttribute('user');

        $roles = Role::where('tenant_id', $user->tenant_id)
            ->with('permissions:id,name,slug,module')
            ->withCount('users')
            ->get();

        return $this->success($response, ['roles' => $roles]);
    }

    /**
     * Create a new role
     * POST /api/v1/roles
     */
    public function store(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');

        $rules = [
            'name' => 'required|max:100',
            'slug' => 'required|max:100',
            'description' => 'max:500',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            // Check if slug already exists for this tenant
            if (Role::where('tenant_id', $currentUser->tenant_id)->where('slug', $data['slug'])->exists()) {
                return $this->error($response, 'Role slug already exists', 400);
            }

            $role = new Role();
            $role->tenant_id = $currentUser->tenant_id;
            $role->name = $data['name'];
            $role->slug = $data['slug'];
            $role->description = $data['description'] ?? null;
            $role->is_system = false;
            $role->save();

            // Assign permissions if provided
            if (!empty($data['permission_ids']) && is_array($data['permission_ids'])) {
                foreach ($data['permission_ids'] as $permissionId) {
                    DB::table('role_permission')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->audit->log(
                'role',
                $role->id,
                'created',
                null,
                $role->toArray(),
                $currentUser->id,
                $currentUser->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            $role->load('permissions');

            return $this->success($response, $role, 'Role created successfully', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Role creation failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to create role', 500);
        }
    }

    /**
     * Get a specific role
     * GET /api/v1/roles/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $currentUser = $request->getAttribute('user');
        $id = $args['id'];

        $role = Role::where('tenant_id', $currentUser->tenant_id)
            ->with(['permissions:id,name,slug,module', 'users:id,first_name,last_name,email'])
            ->find($id);

        if (!$role) {
            return $this->notFound($response, 'Role not found');
        }

        return $this->success($response, $role);
    }

    /**
     * Update a role
     * PUT /api/v1/roles/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $data = $request->getParsedBody();
        $currentUser = $request->getAttribute('user');
        $id = $args['id'];

        $role = Role::where('tenant_id', $currentUser->tenant_id)->find($id);

        if (!$role) {
            return $this->notFound($response, 'Role not found');
        }

        // Prevent modification of system roles
        if ($role->is_system) {
            return $this->error($response, 'Cannot modify system roles', 403);
        }

        $rules = [
            'name' => 'max:100',
            'description' => 'max:500',
        ];

        $errors = $this->validator->validate($data, $rules);

        if (!$this->validator->passes($errors)) {
            return $this->validationError($response, $this->validator->formatErrors($errors));
        }

        try {
            DB::beginTransaction();

            $oldValues = $role->toArray();

            if (isset($data['name'])) $role->name = $data['name'];
            if (isset($data['description'])) $role->description = $data['description'];

            $role->save();

            // Update permissions if provided
            if (isset($data['permission_ids']) && is_array($data['permission_ids'])) {
                DB::table('role_permission')->where('role_id', $role->id)->delete();

                foreach ($data['permission_ids'] as $permissionId) {
                    DB::table('role_permission')->insert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            $this->audit->log(
                'role',
                $role->id,
                'updated',
                $oldValues,
                $role->toArray(),
                $currentUser->id,
                $currentUser->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            $role->load('permissions');

            return $this->success($response, $role, 'Role updated successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Role update failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to update role', 500);
        }
    }

    /**
     * Delete a role
     * DELETE /api/v1/roles/{id}
     */
    public function destroy(Request $request, Response $response, array $args): Response
    {
        $currentUser = $request->getAttribute('user');
        $id = $args['id'];

        $role = Role::where('tenant_id', $currentUser->tenant_id)->find($id);

        if (!$role) {
            return $this->notFound($response, 'Role not found');
        }

        // Prevent deletion of system roles
        if ($role->is_system) {
            return $this->error($response, 'Cannot delete system roles', 403);
        }

        // Check if role has users
        $userCount = DB::table('role_user')->where('role_id', $role->id)->count();
        if ($userCount > 0) {
            return $this->error($response, "Cannot delete role with {$userCount} assigned user(s)", 400);
        }

        try {
            DB::beginTransaction();

            $roleData = $role->toArray();
            $role->delete();

            $this->audit->log(
                'role',
                $role->id,
                'deleted',
                $roleData,
                null,
                $currentUser->id,
                $currentUser->tenant_id,
                $this->getClientIp($request),
                $request->getHeaderLine('User-Agent')
            );

            DB::commit();

            return $this->success($response, null, 'Role deleted successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->logError('Role deletion failed: ' . $e->getMessage());
            return $this->error($response, 'Failed to delete role', 500);
        }
    }

    /**
     * List all permissions
     * GET /api/v1/permissions
     */
    public function permissions(Request $request, Response $response): Response
    {
        $permissions = Permission::orderBy('module')->orderBy('name')->get();

        // Group by module
        $groupedPermissions = $permissions->groupBy('module');

        return $this->success($response, [
            'permissions' => $permissions,
            'grouped_permissions' => $groupedPermissions,
        ]);
    }
}
