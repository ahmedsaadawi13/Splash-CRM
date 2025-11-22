<?php

namespace Database\Seeders;

use Illuminate\Database\Capsule\Manager as DB;

class PermissionsSeeder
{
    public function run()
    {
        echo "→ Seeding permissions...\n";

        $modules = [
            'leads' => ['view', 'create', 'edit', 'delete', 'export'],
            'contacts' => ['view', 'create', 'edit', 'delete', 'export'],
            'accounts' => ['view', 'create', 'edit', 'delete', 'export'],
            'opportunities' => ['view', 'create', 'edit', 'delete', 'export'],
            'activities' => ['view', 'create', 'edit', 'delete'],
            'products' => ['view', 'create', 'edit', 'delete'],
            'quotes' => ['view', 'create', 'edit', 'delete', 'approve'],
            'invoices' => ['view', 'create', 'edit', 'delete'],
            'reports' => ['view', 'create', 'edit', 'delete', 'run'],
            'workflows' => ['view', 'create', 'edit', 'delete', 'execute'],
            'users' => ['view', 'create', 'edit', 'delete'],
            'roles' => ['view', 'create', 'edit', 'delete'],
            'settings' => ['view', 'edit'],
        ];

        foreach ($modules as $module => $actions) {
            foreach ($actions as $action) {
                $slug = "{$module}.{$action}";
                $name = ucfirst($action) . ' ' . ucfirst($module);

                DB::table('permissions')->insertOrIgnore([
                    'name' => $name,
                    'slug' => $slug,
                    'module' => $module,
                    'description' => "Permission to {$action} {$module}",
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        echo "✓ Permissions seeded\n";
    }
}
