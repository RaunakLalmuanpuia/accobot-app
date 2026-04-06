<?php

namespace App\Providers;

use App\Mail\GmailTransport;
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

        Mail::extend('gmail', function () {
            return new GmailTransport(
                clientId: config('mail.mailers.gmail.client_id'),
                clientSecret: config('mail.mailers.gmail.client_secret'),
                refreshToken: config('mail.mailers.gmail.refresh_token'),
            );
        });
    }
}
