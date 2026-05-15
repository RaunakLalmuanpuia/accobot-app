<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Platform admins are never blocked
        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        $routeName = $request->route()->getName();

        // Billing, logout, and onboarding-dismiss routes bypass the gate
        if ($this->isBypassRoute($routeName)) {
            return $next($request);
        }

        $tenant = $request->route('tenant');

        if (! ($tenant instanceof Tenant)) {
            return $next($request);
        }

        $subscription = $tenant->subscription()->with('plan', 'addons.plan')->first();

        // isAccessible() covers active + on-trial.
        // pending is intentionally excluded: billing.* routes already bypass this middleware,
        // so legitimate checkout flows are unaffected. Excluding pending ensures admin-initiated
        // rebills properly block the tenant until the webhook activates the new subscription.
        if ($subscription && $subscription->isAccessible()) {
            return $next($request);
        }

        return redirect()->route('billing.select-plan', $tenant);
    }

    private function isBypassRoute(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        return str_starts_with($name, 'billing.')
            || $name === 'onboarding.dismiss'
            || $name === 'logout';
    }
}
