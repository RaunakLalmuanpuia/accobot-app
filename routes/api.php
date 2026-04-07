<?php

use App\Http\Controllers\Api\MobileAuthController;
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
