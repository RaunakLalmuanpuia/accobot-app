<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TeamMemberController;
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

// ── Admin (no business context) ───────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'adminIndex'])->name('admin.dashboard');
});

// ── Business-scoped ───────────────────────────────────────────────────
Route::middleware(['auth', 'verified', 'member'])
    ->prefix('b/{business}')
    ->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('/settings/team', [TeamMemberController::class, 'index'])->name('team.index')->middleware('biz.permission:view users');
        Route::put('/settings/team/{user}', [TeamMemberController::class, 'update'])->name('team.update')->middleware('biz.permission:assign roles');
        Route::delete('/settings/team/{user}', [TeamMemberController::class, 'destroy'])->name('team.destroy')->middleware('biz.permission:remove user');

        Route::post('/invitations', [InvitationController::class, 'store'])->name('invitation.store')->middleware('biz.permission:invite user');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitation.destroy')->middleware('biz.permission:invite user');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index')->middleware('biz.permission:assign roles');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store')->middleware('biz.permission:assign roles');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update')->middleware('biz.permission:assign roles');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy')->middleware('biz.permission:assign roles');

        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index')->middleware('biz.permission:view clients');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store')->middleware('biz.permission:create client');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update')->middleware('biz.permission:edit client');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')->middleware('biz.permission:delete client');

        Route::get('/vendors', [VendorController::class, 'index'])->name('vendors.index')->middleware('biz.permission:view vendors');
        Route::post('/vendors', [VendorController::class, 'store'])->name('vendors.store')->middleware('biz.permission:create vendor');
        Route::put('/vendors/{vendor}', [VendorController::class, 'update'])->name('vendors.update')->middleware('biz.permission:edit vendor');
        Route::delete('/vendors/{vendor}', [VendorController::class, 'destroy'])->name('vendors.destroy')->middleware('biz.permission:delete vendor');

    });

// ── Invitations (public token link, auth checked in controller) ───────
Route::get('/invite/{token}', [InvitationController::class, 'show'])->name('invitation.show');
Route::post('/invite/{token}/accept', [InvitationController::class, 'accept'])->name('invitation.accept');
Route::delete('/invite/{token}/decline', [InvitationController::class, 'decline'])->middleware('auth')->name('invitation.decline');

// ── Profile (auth only, no business required) ─────────────────────────
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
