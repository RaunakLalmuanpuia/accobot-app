<?php

namespace App\Http\Controllers;

use App\Models\User;

abstract class Controller
{
    /**
     * Resolve the correct post-auth home URL for a user.
     * Used by email verification and password confirm controllers.
     */
    protected function homeUrl(User $user): string
    {
        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        $business = $user->lastBusiness ?? $user->businesses()->first();

        return $business
            ? route('dashboard', ['business' => $business->id])
            : route('profile.edit');
    }
}
