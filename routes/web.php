<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\VendorController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


// ── Welcome ───────────────────────────────────────────────────────────
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'    => Route::has('login'),
        'canRegister' => Route::has('register'),
    ]);
});

// ── Auth (guest) ──────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.store');
    Route::get('register', [AuthController::class, 'registerForm'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.store');
});

// ── Auth (authenticated) ──────────────────────────────────────────────
Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});

// ── Admin (no tenant context) ─────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'adminIndex'])->name('admin.dashboard');

    // Impersonation — Super Admin only
    Route::post('/admin/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
});

// Stop impersonation (available to anyone with an active impersonation session)
Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonate.stop');

// ── Tenant-scoped ─────────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'member'])
    ->prefix('t/{tenant}')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/settings/team', [TeamMemberController::class, 'index'])->name('team.index')->middleware('tenant.permission:members.view');
        Route::get('/settings/team/check-email', [TeamMemberController::class, 'checkEmail'])->name('team.check-email')->middleware('tenant.permission:members.invite');
        Route::post('/settings/team', [TeamMemberController::class, 'store'])->name('team.store')->middleware('tenant.permission:members.invite');
        Route::put('/settings/team/{user}', [TeamMemberController::class, 'update'])->name('team.update')->middleware('tenant.permission:members.assign_role');
        Route::delete('/settings/team/{user}', [TeamMemberController::class, 'destroy'])->name('team.destroy')->middleware(['tenant.permission:members.remove', 'no.impersonate']);

        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitation.store')->middleware('tenant.permission:members.invite');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitation.destroy')->middleware('tenant.permission:members.invite');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('tenant.permission:members.assign_role');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('tenant.permission:members.assign_role');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('tenant.permission:members.assign_role');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware(['tenant.permission:members.assign_role', 'no.impersonate']);

        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index')->middleware('tenant.permission:clients.view');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store')->middleware('tenant.permission:clients.create');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update')->middleware('tenant.permission:clients.edit');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')->middleware(['tenant.permission:clients.delete', 'no.impersonate']);

        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index')->middleware('tenant.permission:vendors.view');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store')->middleware('tenant.permission:vendors.create');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update')->middleware('tenant.permission:vendors.edit');
        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy')->middleware(['tenant.permission:vendors.delete', 'no.impersonate']);

        // ── Accounting Assistant (Chat) ────────────────────────────────
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index')->middleware('tenant.permission:chat.view');
        Route::post('/chat', [ChatController::class, 'chat'])->name('chat.store')->middleware('tenant.permission:chat.view');

        // ── Invoice PDF download ───────────────────────────────────────
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

    });

// ── Invitations (public token link, auth checked in controller) ───────
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/invite/{rawToken}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invite/{rawToken}/accept', [InvitationController::class, 'accept'])->name('invitation.accept');
    Route::delete('/invite/{rawToken}/decline', [InvitationController::class, 'decline'])->middleware('auth')->name('invitation.decline');
});

// ── Invitations (bell dropdown — auth + ID-based, no raw token) ────────
Route::middleware('auth')->group(function () {
    Route::post('/invite/{invitation}/accept', [InvitationController::class, 'acceptById'])->name('invitation.accept-by-id');
    Route::delete('/invite/{invitation}/decline', [InvitationController::class, 'declineById'])->name('invitation.decline-by-id');
});

// ── Profile (auth only, no tenant required) ───────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
