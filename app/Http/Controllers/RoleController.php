<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Tenant;
use App\Models\TenantRolePermission;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['admin', 'owner', 'TenantAdmin'];

    private function permissionGroups(): array
    {
        return [
            'Tenant / Settings' => [
                'tenant.view_settings',
                'tenant.update_settings',
            ],
            'Members / Roles' => [
                'members.view',
                'members.invite',
                'members.remove',
                'members.suspend',
                'members.assign_role',
            ],
            'CA Client Linking' => [
                'clients.view_requests',
                'clients.approve_link',
                'clients.terminate_link',
            ],
            'Clients' => [
                'clients.view',
                'clients.create',
                'clients.edit',
                'clients.delete',
            ],
            'Vendors' => [
                'vendors.view',
                'vendors.create',
                'vendors.edit',
                'vendors.delete',
            ],
            'Inventory' => [
                'products.view',
                'products.create',
                'products.edit',
                'products.delete',
            ],
            'Narration Heads' => [
                'narration_heads.view',
                'narration_heads.create',
                'narration_heads.edit',
                'narration_heads.delete',
            ],
            'Banking' => [
                'transactions.view',
                'transactions.review',
                'transactions.edit',
                'transactions.import',
            ],
            'Accounting' => [
                'invoices.view',
                'invoices.create',
                'invoices.edit',
                'invoices.delete',
                'reports.view',
                'reports.export',
            ],
            'Accounting Assistant' => [
                'chat.view',
            ],
            'Integrations' => [
                'integrations.view',
                'integrations.manage',
            ],
            'Audit' => [
                'audit.view',
            ],
        ];
    }

    public function index(Tenant $tenant)
    {
        $allPermissions = Permission::all()->keyBy('name');

        $permissionGroups = collect($this->permissionGroups())
            ->map(fn($names, $group) => [
                'group'       => $group,
                'permissions' => collect($names)->map(fn($name) => $allPermissions->get($name))->filter()->values(),
            ])
            ->values();

        $overrides = TenantRolePermission::where('tenant_id', $tenant->id)
            ->with('permission')
            ->get()
            ->groupBy('role_id');

        $roles = Role::with('permissions')->get()->map(function (Role $role) use ($overrides) {
            $tenantOverride = $overrides->get($role->id);

            $effectivePermissions = $tenantOverride
                ? $tenantOverride->map(fn($trp) => ['id' => $trp->permission->id, 'name' => $trp->permission->name])->values()
                : $role->permissions->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values();

            return [
                'id'            => $role->id,
                'name'          => $role->name,
                'permissions'   => $effectivePermissions,
                'is_customized' => $tenantOverride !== null,
            ];
        });

        return inertia('Roles/Index', [
            'tenant'           => $tenant,
            'roles'            => $roles,
            'permissionGroups' => $permissionGroups,
            'systemRoles'      => self::SYSTEM_ROLES,
        ]);
    }

    public function store(Request $request, Tenant $tenant)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
        ]);

        $role = Role::create(['name' => $request->name]);

        $this->syncTenantPermissions($tenant->id, $role->id, $request->permissions ?? []);

        AuditEvent::log('role.created', ['id' => $role->id, 'name' => $role->name]);

        return back();
    }

    public function update(Request $request, Tenant $tenant, Role $role)
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES), 403, 'System roles cannot be modified.');

        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
        ]);

        $role->update(['name' => $request->name]);

        $this->syncTenantPermissions($tenant->id, $role->id, $request->permissions ?? []);

        AuditEvent::log('role.updated', [
            'id'          => $role->id,
            'name'        => $role->name,
            'permissions' => $request->permissions ?? [],
        ]);

        return back();
    }

    public function destroy(Tenant $tenant, Role $role)
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES), 403, 'System roles cannot be deleted.');

        AuditEvent::log('role.deleted', ['id' => $role->id, 'name' => $role->name]);

        TenantRolePermission::where('tenant_id', $tenant->id)
            ->where('role_id', $role->id)
            ->delete();

        $role->delete();

        return back();
    }

    private function syncTenantPermissions(string $tenantId, int $roleId, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        TenantRolePermission::where('tenant_id', $tenantId)
            ->where('role_id', $roleId)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            TenantRolePermission::create([
                'tenant_id'     => $tenantId,
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
