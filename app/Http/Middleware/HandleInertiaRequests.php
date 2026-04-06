<?php

namespace App\Http\Middleware;

use App\Models\BusinessRolePermission;
use App\Models\Invitation;
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
        $user     = $request->user();
        $business = $request->route('business'); // resolved via route model binding

        return [
            ...parent::share($request),

            'auth' => [
                'user'     => $user,
                'is_admin' => $user?->hasRole('admin') ?? false,

                // All businesses the user belongs to (for the switcher)
                'businesses' => $user && !$user->hasRole('admin')
                    ? $user->businesses()->select('businesses.id', 'businesses.name')->get()
                    : [],

                // Current business comes from the URL, not session
                'current_business_id' => $business?->id,

                // Pending invitations for this user (to show in-app notifications)
                'pending_invitations' => $user && !$user->hasRole('admin')
                    ? Invitation::with(['business', 'role'])
                        ->where('email', $user->email)
                        ->whereNull('accepted_at')
                        ->where('expires_at', '>', now())
                        ->get()
                        ->map(fn($inv) => [
                            'token'         => $inv->token,
                            'business_name' => $inv->business->name,
                            'role_name'     => $inv->role->name,
                        ])
                    : [],

                // Admin gets all permissions; others get their role's permissions for this business
                'permissions' => match (true) {
                    $user === null          => [],
                    $user->hasRole('admin') => Permission::pluck('name'),
                    $business === null      => [],
                    default => (function () use ($user, $business) {
                        $businessRole = $user->businessRoles()
                            ->where('business_id', $business->id)
                            ->with('role.permissions')
                            ->first();

                        if (!$businessRole) return collect();

                        $override = BusinessRolePermission::where('business_id', $business->id)
                            ->where('role_id', $businessRole->role_id)
                            ->with('permission')
                            ->get();

                        return $override->isNotEmpty()
                            ? $override->pluck('permission.name')->unique()->values()
                            : $businessRole->role->permissions->pluck('name')->values();
                    })(),
                },
            ],
        ];
    }
}
