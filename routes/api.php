<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\TenantBankingController;
use App\Http\Controllers\Api\TenantChatController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Mobile + Integration
|--------------------------------------------------------------------------
|
| These routes are stateless (Sanctum PAT bearer token).
| Web SPA uses cookie-based auth via web.php routes.
|
*/

// ── Mobile auth (public) ──────────────────────────────────────────────
Route::prefix('mobile')->name('api.mobile.')->middleware('throttle:10,1')->group(function () {
    Route::post('login', [MobileAuthController::class, 'login'])->name('login');
});

// ── Mobile auth (authenticated) ───────────────────────────────────────
Route::prefix('mobile')->name('api.mobile.')->middleware(['auth:sanctum'])->group(function () {
    Route::get('me',                         [MobileAuthController::class, 'me'])->name('me');
    Route::post('logout',                    [MobileAuthController::class, 'logout'])->name('logout');
    Route::get('tokens',                     [MobileAuthController::class, 'tokens'])->name('tokens');
    Route::delete('tokens/{tokenId}',        [MobileAuthController::class, 'revokeToken'])->name('tokens.revoke');
    Route::delete('tokens',                  [MobileAuthController::class, 'revokeAll'])->name('tokens.revoke_all');
});

// ── Tenant-scoped routes ───────────────────────────────────────────────
// Requires: Bearer token + user must belong to the tenant (or be platform admin)
Route::prefix('mobile/tenants/{tenant}')
    ->name('api.mobile.tenant.')
    ->middleware(['auth:sanctum', 'member'])
    ->group(function () {

        // ── Chat ──────────────────────────────────────────────────────
        // POST  /api/mobile/tenants/{tenant}/chat
        //       Send a message; returns SSE stream (text/event-stream)
        Route::post('chat', [TenantChatController::class, 'chat'])
            ->name('chat')
            ->middleware('tenant.permission:chat.view');

        // ── Banking: read ─────────────────────────────────────────────
        // GET   /api/mobile/tenants/{tenant}/banking/pending
        // GET   /api/mobile/tenants/{tenant}/banking/narration-heads
        Route::prefix('banking')->name('banking.')->middleware('tenant.permission:transactions.view')->group(function () {
            Route::get('pending',         [TenantBankingController::class, 'pending'])->name('pending');
            Route::get('narration-heads', [TenantBankingController::class, 'narrationHeads'])->name('narration-heads');
        });

        // ── Banking: ingest ───────────────────────────────────────────
        // POST  /api/mobile/tenants/{tenant}/banking/ingest/sms
        // POST  /api/mobile/tenants/{tenant}/banking/ingest/email
        // POST  /api/mobile/tenants/{tenant}/banking/ingest/statement
        Route::prefix('banking/ingest')->name('banking.ingest.')->middleware('tenant.permission:transactions.import')->group(function () {
            Route::post('sms',       [TenantBankingController::class, 'ingestSms'])->name('sms');
            Route::post('email',     [TenantBankingController::class, 'ingestEmail'])->name('email');
            Route::post('statement', [TenantBankingController::class, 'ingestStatement'])->name('statement');
        });

        // ── Banking: review ───────────────────────────────────────────
        // POST  /api/mobile/tenants/{tenant}/banking/transactions/{transaction}/approve
        // POST  /api/mobile/tenants/{tenant}/banking/transactions/{transaction}/correct
        Route::prefix('banking/transactions/{transaction}')->name('banking.transactions.')->group(function () {
            Route::post('approve', [TenantBankingController::class, 'approve'])->name('approve');
            Route::post('correct', [TenantBankingController::class, 'correct'])->name('correct');
        });
    });
