<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TenantPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user   = $request->user();
        $tenant = $request->route('tenant');

        abort_if(! $user || ! $tenant, 403);

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            abort_unless($user->hasPermissionInTenant($permission, $tenant), 403);
        }

        return $next($request);
    }
}
