<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Roles and starter permissions from docs section 10. V1 primarily
     * exercises ADMIN and AGRONOMIST; the rest are seeded so the permission
     * model doesn't need a migration when they come into use.
     */
    public function run(): void
    {
        // A previous request/command may have cached an empty permission
        // list (e.g. before this table existed). Clear it so this seeder
        // is safe to (re-)run regardless of cache state.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.manage',
            'master_data.manage',
            'plans.view',
            'plans.create',
            'activities.view',
            'activities.create',
            'activities.verify',
            'reports.view',
            'announcements.send',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $rolePermissions = [
            'SUPER_ADMIN' => $permissions,
            'ADMIN' => [
                'users.manage', 'master_data.manage', 'plans.view',
                'activities.view', 'activities.verify', 'reports.view', 'announcements.send',
            ],
            'MANAGER' => ['plans.view', 'activities.view', 'reports.view'],
            'SUPERVISOR' => ['plans.view', 'activities.view', 'activities.verify', 'reports.view'],
            // *.view means "can see everyone's records" (monitoring). A field
            // agronomist doesn't need it to see their own — *.create implies
            // that — so it's deliberately left off here; granting it would
            // widen an agronomist's visibility to the whole team's plans.
            'AGRONOMIST' => ['plans.create', 'activities.create'],
        ];

        foreach ($rolePermissions as $role => $rolePerms) {
            Role::findOrCreate($role)->syncPermissions($rolePerms);
        }
    }
}
