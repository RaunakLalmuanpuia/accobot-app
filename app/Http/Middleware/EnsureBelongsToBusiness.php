<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureBelongsToBusiness
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Platform admin has access to all businesses
        if ($user->hasRole('admin')) {
            return $next($request);
        }

        $business = $request->route('business');

        if ($business && !$user->businesses()->where('businesses.id', $business->id)->exists()) {
            abort(403, 'You do not belong to this business.');
        }

        // Track last visited business
        if ($business && $user->last_business_id !== $business->id) {
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $user->id)
                ->update(['last_business_id' => $business->id]);
        }

        return $next($request);
    }
}
