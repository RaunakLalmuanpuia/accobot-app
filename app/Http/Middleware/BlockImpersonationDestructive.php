<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Block destructive actions while a Super Admin is impersonating a user.
 *
 * Apply to routes that perform: tenant deletion, link termination,
 * owner removal/demotion, mass token revocation.
 */
class BlockImpersonationDestructive
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (session()->has('impersonator_id')) {
            abort(403, 'Destructive actions are not allowed while impersonating a user.');
        }

        return $next($request);
    }
}
