<?php

namespace App\Providers;

use App\Mail\GmailTransport;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\TallyAttendanceType;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyPayHead;
use App\Models\TallyStatutoryMaster;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyVoucher;
use App\Models\Vendor;
use App\Observers\TallyAccobotObserver;
use App\Observers\TallyModelObserver;
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
        // Tally outbound change tracking
        foreach ([
            TallyLedgerGroup::class,
            TallyLedger::class,
            TallyStockGroup::class,
            TallyStockCategory::class,
            TallyStockItem::class,
            TallyVoucher::class,
            TallyStatutoryMaster::class,
            TallyEmployeeGroup::class,
            TallyEmployee::class,
            TallyPayHead::class,
            TallyAttendanceType::class,
        ] as $model) {
            $model::observe(TallyModelObserver::class);
        }

        foreach ([Client::class, Vendor::class, Product::class, Invoice::class] as $model) {
            $model::observe(TallyAccobotObserver::class);
        }

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
