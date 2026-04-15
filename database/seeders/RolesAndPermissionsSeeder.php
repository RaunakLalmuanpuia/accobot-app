<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds global roles and their DEFAULT permissions per the design doc.
 *
 * Tenant-specific overrides are stored in tenant_role_permissions.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ─── Permission catalog ───────────────────────────────────────

        $permissions = [
            // Tenant / Settings
            'tenant.view_settings',
            'tenant.update_settings',

            // Members / Roles
            'members.view',
            'members.invite',
            'members.remove',
            'members.suspend',
            'members.assign_role',

            // CA Client Linking (business side — Owner-only by policy)
            'clients.view_requests',
            'clients.approve_link',
            'clients.terminate_link',

            // Clients (business contacts/customers)
            'clients.view',
            'clients.create',
            'clients.edit',
            'clients.delete',

            // Vendors
            'vendors.view',
            'vendors.create',
            'vendors.edit',
            'vendors.delete',

            // Products / Inventory
            'products.view',
            'products.create',
            'products.edit',
            'products.delete',

            // Accounting
            'invoices.view',
            'invoices.create',
            'invoices.edit',
            'invoices.delete',
            'reports.view',
            'reports.export',

            // Integrations
            'integrations.view',
            'integrations.manage',

            // Audit
            'audit.view',

            // Accounting Assistant (Chat)
            'chat.view',

            // Banking / Narration
            'transactions.view',
            'transactions.review',  // approve / reject a pending transaction
            'transactions.edit',    // correct narration details, link invoices
            'transactions.import',  // upload statements, paste SMS / email
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // ─── Platform admin ───────────────────────────────────────────

        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->syncPermissions($permissions);

        // ─── Business tenant presets ──────────────────────────────────

        $owner = Role::firstOrCreate(['name' => 'owner']);
        $owner->syncPermissions($permissions);

        /**
         * TenantAdmin — operations admin, no CA approval rights.
         * Named TenantAdmin (not Admin) to avoid MySQL case-insensitive collision with 'admin'.
         */
        $bizAdmin = Role::firstOrCreate(['name' => 'TenantAdmin']);
        $bizAdmin->syncPermissions([
            'tenant.view_settings',
            'tenant.update_settings',
            'members.view',
            'members.invite',
            'members.remove',
            'members.suspend',
            'members.assign_role',
            'clients.view_requests',
            'clients.view',   'clients.create',   'clients.edit',   'clients.delete',
            'vendors.view',   'vendors.create',   'vendors.edit',   'vendors.delete',
            'products.view',  'products.create',  'products.edit',  'products.delete',
            'invoices.view',  'invoices.create',  'invoices.edit',  'invoices.delete',
            'reports.view',   'reports.export',
            'integrations.view', 'integrations.manage',
            'audit.view',
            'chat.view',
            'transactions.view', 'transactions.review', 'transactions.edit', 'transactions.import',
        ]);

        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $manager->syncPermissions([
            'members.view',
            'clients.view',   'clients.create',   'clients.edit',
            'vendors.view',   'vendors.create',   'vendors.edit',
            'products.view',  'products.create',  'products.edit',
            'invoices.view',  'invoices.create',  'invoices.edit',
            'reports.view',   'reports.export',
            'integrations.view',
            'chat.view',
            'transactions.view', 'transactions.review', 'transactions.edit', 'transactions.import',
        ]);

        $staff = Role::firstOrCreate(['name' => 'Staff']);
        $staff->syncPermissions([
            'clients.view',  'clients.create',
            'vendors.view',  'vendors.create',
            'products.view', 'products.create',
            'invoices.view', 'invoices.create', 'invoices.edit',
            'reports.view',
            'transactions.view', 'transactions.review',
        ]);

        $viewer = Role::firstOrCreate(['name' => 'Viewer']);
        $viewer->syncPermissions([
            'clients.view',
            'vendors.view',
            'products.view',
            'invoices.view',
            'reports.view',
            'transactions.view',
        ]);

        /**
         * ExternalAccountant — CA staff inside a business tenant (safe read-only baseline).
         */
        $extAccountant = Role::firstOrCreate(['name' => 'ExternalAccountant']);
        $extAccountant->syncPermissions([
            'clients.view',
            'vendors.view',
            'products.view',
            'invoices.view',
            'reports.view',
            'reports.export',
            'transactions.view',
        ]);

        // ─── CA firm tenant presets ───────────────────────────────────

        $ownerPartner = Role::firstOrCreate(['name' => 'OwnerPartner']);
        $ownerPartner->syncPermissions([
            'tenant.view_settings',
            'tenant.update_settings',
            'members.view',
            'members.invite',
            'members.remove',
            'members.suspend',
            'members.assign_role',
            'clients.view',
            'vendors.view',
            'products.view',
            'reports.view',   'reports.export',
            'integrations.view', 'integrations.manage',
            'audit.view',
        ]);

        $caManager = Role::firstOrCreate(['name' => 'CAManager']);
        $caManager->syncPermissions([
            'members.view',
            'clients.view',
            'vendors.view',
            'products.view',
            'reports.view',
            'reports.export',
        ]);

        $auditor = Role::firstOrCreate(['name' => 'Auditor']);
        $auditor->syncPermissions([
            'clients.view',
            'vendors.view',
            'products.view',
            'invoices.view',
            'reports.view',
            'reports.export',
        ]);

        $caStaff = Role::firstOrCreate(['name' => 'CAStaff']);
        $caStaff->syncPermissions([
            'clients.view',
            'vendors.view',
            'products.view',
            'reports.view',
        ]);

        $integration = Role::firstOrCreate(['name' => 'IntegrationUser']);
        $integration->syncPermissions([
            'clients.view',
            'vendors.view',
            'products.view',
            'invoices.view',
            'invoices.create',
            'reports.view',
        ]);
    }
}
