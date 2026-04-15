<?php

namespace App\Providers;

use App\Mail\GmailTransport;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RedirectIfAuthenticated::redirectUsing(function (Request $request) {
            $user = Auth::user();

            if ($user->hasRole('admin')) {
                return route('admin.dashboard');
            }

            $tenant = $user->lastTenant ?? $user->tenants()->first();

            return $tenant?->id
                ? route('dashboard', ['tenant' => $tenant->id])
                : route('profile.edit');
        });

        Mail::extend('gmail', function () {
            return new GmailTransport(
                clientId: config('mail.mailers.gmail.client_id'),
                clientSecret: config('mail.mailers.gmail.client_secret'),
                refreshToken: config('mail.mailers.gmail.refresh_token'),
            );
        });
    }
}
