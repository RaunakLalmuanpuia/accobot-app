<?php

namespace App\Http\Middleware;

use App\Models\Invitation;
use App\Models\TenantRolePermission;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user   = $request->user();
        $tenant = $request->route('tenant'); // resolved via route model binding

        return [
            ...parent::share($request),

            'flash' => [
                'success' => session('success'),
                'error'   => session('error'),
            ],

            'auth' => [
                'user'     => $user,
                'is_admin' => $user?->hasRole('admin') ?? false,

                // Impersonation banner data
                'impersonating' => session()->has('impersonator_id'),

                // All tenants the user belongs to (for the switcher)
                'tenants' => $user && ! $user->hasRole('admin')
                    ? $user->tenants()->select('tenants.id', 'tenants.name', 'tenants.type')->get()
                    : [],

                // Current tenant comes from the URL, not session
                'current_tenant_id' => $tenant?->id,

                // Pending invitations for this user (in-app notifications)
                'pending_invitations' => $user && ! $user->hasRole('admin')
                    ? Invitation::with(['tenant', 'role'])
                        ->where('email', $user->email)
                        ->where('status', 'pending')
                        ->where('expires_at', '>', now())
                        ->get()
                        ->map(fn($inv) => [
                            'id'          => $inv->id,
                            'tenant_name' => $inv->tenant->name,
                            'role_name'   => $inv->role->name,
                        ])
                    : [],

                // Permission group definitions for the dashboard access card
                'permission_groups' => config('permission_groups'),

                // Admin gets all permissions; others get effective permissions for this tenant
                'permissions' => match (true) {
                    $user === null          => [],
                    $user->hasRole('admin') => Permission::pluck('name'),
                    $tenant === null        => [],
                    default => (function () use ($user, $tenant) {
                        $tenantRole = $user->tenantRoles()
                            ->where('tenant_id', $tenant->id)
                            ->with('role.permissions')
                            ->first();

                        if (! $tenantRole) return collect();

                        $override = TenantRolePermission::where('tenant_id', $tenant->id)
                            ->where('role_id', $tenantRole->role_id)
                            ->with('permission')
                            ->get();

                        return $override->isNotEmpty()
                            ? $override->pluck('permission.name')->unique()->values()
                            : $tenantRole->role->permissions->pluck('name')->values();
                    })(),
                },
            ],
        ];
    }
}
