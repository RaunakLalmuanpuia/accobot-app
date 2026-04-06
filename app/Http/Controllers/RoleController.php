<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\BusinessRolePermission;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    private const SYSTEM_ROLES = ['admin', 'owner'];

    private function permissionGroups(): array
    {
        return [
            'Dashboard'       => ['view dashboard'],
            'Clients'         => ['view clients', 'create client', 'edit client', 'delete client'],
            'Vendors'         => ['view vendors', 'create vendor', 'edit vendor', 'delete vendor'],
            'Settings'        => ['view settings', 'manage settings'],
            'User Management' => ['view users', 'invite user', 'remove user', 'assign roles'],
        ];
    }

    public function index(Business $business)
    {
        $allPermissions = Permission::all()->keyBy('name');

        $permissionGroups = collect($this->permissionGroups())
            ->map(fn($names, $group) => [
                'group'       => $group,
                'permissions' => collect($names)->map(fn($name) => $allPermissions->get($name))->filter()->values(),
            ])
            ->values();

        // Business-specific permission overrides grouped by role_id
        $overrides = BusinessRolePermission::where('business_id', $business->id)
            ->with('permission')
            ->get()
            ->groupBy('role_id');

        $roles = Role::with('permissions')->get()->map(function (Role $role) use ($overrides) {
            $businessOverride = $overrides->get($role->id);

            // Use business-specific permissions if set, otherwise fall back to global
            $effectivePermissions = $businessOverride
                ? $businessOverride->map(fn($brp) => ['id' => $brp->permission->id, 'name' => $brp->permission->name])->values()
                : $role->permissions->map(fn($p) => ['id' => $p->id, 'name' => $p->name])->values();

            return [
                'id'           => $role->id,
                'name'         => $role->name,
                'permissions'  => $effectivePermissions,
                'is_customized'=> $businessOverride !== null,
            ];
        });

        return inertia('Roles/Index', [
            'business'         => $business,
            'roles'            => $roles,
            'permissionGroups' => $permissionGroups,
            'systemRoles'      => self::SYSTEM_ROLES,
        ]);
    }

    public function store(Request $request, Business $business)
    {
        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name',
            'permissions' => 'array',
        ]);

        $role = Role::create(['name' => $request->name]);

        $this->syncBusinessPermissions($business->id, $role->id, $request->permissions ?? []);

        return back();
    }

    public function update(Request $request, Business $business, Role $role)
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES), 403, 'System roles cannot be modified.');

        $request->validate([
            'name'        => 'required|string|max:255|unique:roles,name,' . $role->id,
            'permissions' => 'array',
        ]);

        $role->update(['name' => $request->name]);

        $this->syncBusinessPermissions($business->id, $role->id, $request->permissions ?? []);

        return back();
    }

    public function destroy(Business $business, Role $role)
    {
        abort_if(in_array($role->name, self::SYSTEM_ROLES), 403, 'System roles cannot be deleted.');

        BusinessRolePermission::where('business_id', $business->id)
            ->where('role_id', $role->id)
            ->delete();

        $role->delete();

        return back();
    }

    private function syncBusinessPermissions(string $businessId, int $roleId, array $permissionNames): void
    {
        $permissionIds = Permission::whereIn('name', $permissionNames)->pluck('id');

        BusinessRolePermission::where('business_id', $businessId)
            ->where('role_id', $roleId)
            ->delete();

        foreach ($permissionIds as $permissionId) {
            BusinessRolePermission::create([
                'business_id'   => $businessId,
                'role_id'       => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
