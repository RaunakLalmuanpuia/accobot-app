<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureBelongsToTenant
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        // Platform admin has access to all tenants
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $tenant = $request->route('tenant');

        if ($tenant && ! $user->tenants()->where('tenants.id', $tenant->id)->exists()) {
            abort(403, 'You do not belong to this tenant.');
        }

        // Track last visited tenant
        if ($tenant && $user->last_tenant_id !== $tenant->id) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update(['last_tenant_id' => $tenant->id]);
        }

        return $next($request);
    }
}
