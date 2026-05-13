<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckSubscriptionFeature
{
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user   = $request->user();
        $tenant = $request->route('tenant');

        // Platform admins are never blocked
        if ($user && $user->hasRole('admin')) {
            return $next($request);
        }

        if ($tenant && ! $tenant->hasFeature($feature)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Your current plan does not include this feature.',
                    'feature' => $feature,
                ], 403);
            }

            return redirect()
                ->route('billing.select-plan', $tenant)
                ->with('error', 'Your current plan does not include this feature. Please upgrade to continue.');
        }

        return $next($request);
    }
}
