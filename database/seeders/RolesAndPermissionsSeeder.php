<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Seeds global roles and their DEFAULT permissions via Spatie.
 *
 * These serve as fallback when a business has not customised a role.
 * Business-specific overrides are stored in business_role_permissions
 * and seeded in DummyDataSeeder.
 */

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles & permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permissions ─────────────────────────────────────────────

        $permissions = [
            // Dashboard
            'view dashboard',

            // Clients
            'view clients',
            'create client',
            'edit client',
            'delete client',

            // Vendors
            'view vendors',
            'create vendor',
            'edit vendor',
            'delete vendor',

            // Business Settings
            'view settings',
            'manage settings',

            // User Management (within a business)
            'view users',
            'invite user',
            'remove user',
            'assign roles',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ─── Roles & their permissions ────────────────────────────────

        /**
         * Admin — Platform-level super admin.
         * Full access across the system.
         */
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        /**
         * Owner — Business owner.
         * Full control within their own business, including user management.
         */
        $owner = Role::firstOrCreate(['name' => 'owner']);
        $owner->syncPermissions($permissions);

        /**
         * CA (Chartered Accountant) — Senior accountant.
         * Full financial access and reporting. Cannot manage users or business settings.
         */
        $ca = Role::firstOrCreate(['name' => 'ca']);
        $ca->syncPermissions([
            'view dashboard',

            'view clients', 'create client', 'edit client', 'delete client',

            'view vendors', 'create vendor', 'edit vendor', 'delete vendor',

            'view settings',

            'view users', 'invite user',
        ]);

        /**
         * User — Basic staff user.
         * Can create and view day-to-day entries. Cannot delete, manage tax, settings, or users.
         */
        $user = Role::firstOrCreate(['name' => 'user']);
        $user->syncPermissions([
            'view dashboard',

            'view clients', 'create client',

            'view vendors', 'create vendor',
        ]);
    }
}
