<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    // ── Platform admin dashboard ───────────────────────────────────────
    public function adminIndex()
    {
        abort_unless(auth()->user()->hasRole('admin'), 403);

        $tenants = Tenant::withCount('users')
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $roleBreakdown = Role::withCount('users')
            ->having('users_count', '>', 0)
            ->orderByDesc('users_count')
            ->get()
            ->map(fn($r) => ['name' => $r->name, 'count' => $r->users_count]);

        return inertia('Dashboard/Admin', [
            'stats' => [
                'tenants'   => Tenant::count(),
                'businesses' => Tenant::where('type', 'business')->count(),
                'ca_firms'  => Tenant::where('type', 'ca_firm')->count(),
                'users'     => User::where('type', 'human')->count(),
                'roles'     => Role::count(),
            ],
            'tenants'       => $tenants,
            'roleBreakdown' => $roleBreakdown,
            'recentUsers'   => User::where('type', 'human')
                ->latest()
                ->take(5)
                ->get(['id', 'name', 'email', 'status', 'created_at']),
        ]);
    }

    // ── Tenant dashboard (permission-driven) ───────────────────────────
    public function index(Tenant $tenant)
    {
        $user = auth()->user();

        // Resolve effective permissions for this tenant
        $permissions = collect(
            $user->hasRole('admin')
                ? \Spatie\Permission\Models\Permission::pluck('name')->toArray()
                : $this->effectivePermissions($user, $tenant)
        );

        // Resolve role name and membership info
        $tenantRole = $user->tenantRoles()
            ->where('tenant_id', $tenant->id)
            ->with('role')
            ->first();

        $roleName   = $user->hasRole('admin') ? 'admin' : ($tenantRole?->role?->name ?? 'Unknown');
        $memberType = 'internal';

        if (! $user->hasRole('admin')) {
            $pivot = $user->tenants()
                ->where('tenants.id', $tenant->id)
                ->first()?->pivot;
            $memberType = $pivot?->member_type ?? 'internal';
        }

        // Build stats based on what the user can see
        $stats = [];

        if ($permissions->contains('members.view')) {
            $stats['members'] = User::whereHas('tenants', fn($q) => $q->where('tenants.id', $tenant->id))->count();
        }
        if ($permissions->contains('clients.view')) {
            $stats['clients'] = Client::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)->count();
        }
        if ($permissions->contains('vendors.view')) {
            $stats['vendors'] = Vendor::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)->count();
        }

        // Recent members (only if they can view the team)
        $recentMembers = [];
        $roleBreakdown = [];

        if ($permissions->contains('members.view')) {
            $recentMembers = User::whereHas('tenants', fn($q) => $q->where('tenants.id', $tenant->id))
                ->with([
                    'tenantRoles' => fn($q) => $q->where('tenant_id', $tenant->id)->with('role'),
                    'tenants'     => fn($q) => $q->where('tenants.id', $tenant->id),
                ])
                ->latest()
                ->take(5)
                ->get()
                ->map(fn($u) => [
                    'id'          => $u->id,
                    'name'        => $u->name,
                    'email'       => $u->email,
                    'role'        => $u->tenantRoles->first()?->role?->name,
                    'member_type' => $u->tenants->first()?->pivot?->member_type ?? 'internal',
                ]);

            $roleBreakdown = collect($recentMembers)
                ->where('role', '!=', null)
                ->groupBy('role')
                ->map(fn($g, $r) => ['role' => $r, 'count' => $g->count()])
                ->values();
        }

        return inertia('Dashboard/Tenant', [
            'tenant'        => $tenant,
            'roleName'      => $roleName,
            'memberType'    => $memberType,
            'permissions'   => $permissions->values(),
            'stats'         => $stats,
            'recentMembers' => $recentMembers,
            'roleBreakdown' => $roleBreakdown,
        ]);
    }

    // ── Helper ─────────────────────────────────────────────────────────

    private function effectivePermissions(User $user, Tenant $tenant): array
    {
        $tenantRole = $user->tenantRoles()
            ->where('tenant_id', $tenant->id)
            ->with('role.permissions')
            ->first();

        if (! $tenantRole) return [];

        $overrides = \App\Models\TenantRolePermission::where('tenant_id', $tenant->id)
            ->where('role_id', $tenantRole->role_id)
            ->with('permission')
            ->get();

        return $overrides->isNotEmpty()
            ? $overrides->pluck('permission.name')->toArray()
            : $tenantRole->role->permissions->pluck('name')->toArray();
    }
}
