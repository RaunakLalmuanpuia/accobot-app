<?php

use App\Http\Controllers\Admin\AiUsageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailIngestController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NarrationHeadController;
use App\Http\Controllers\NarrationReviewController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SmsIngestController;
use App\Http\Controllers\StatementUploadController;
use App\Http\Controllers\TallyConnectionController;
use App\Http\Controllers\TallyDataController;
use App\Http\Controllers\TallyMasterCrudController;
use App\Http\Controllers\TallySyncController;
use App\Http\Controllers\TallyVoucherCrudController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\TeamMemberController;
use App\Http\Controllers\ProductController;
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
    Route::get('/admin/ai-usage', [AiUsageController::class, 'index'])->name('admin.ai-usage');

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

        Route::get('/settings/audit', [AuditLogController::class, 'index'])->name('settings.audit')->middleware('tenant.permission:audit.view');

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

        Route::get('/products', [ProductController::class, 'index'])->name('products.index')->middleware('tenant.permission:products.view');
        Route::post('/products', [ProductController::class, 'store'])->name('products.store')->middleware('tenant.permission:products.create');
        Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update')->middleware('tenant.permission:products.edit');
        Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy')->middleware(['tenant.permission:products.delete', 'no.impersonate']);

        Route::get('/narration-heads', [NarrationHeadController::class, 'index'])->name('narration-heads.index')->middleware('tenant.permission:narration_heads.view');
        Route::post('/narration-heads', [NarrationHeadController::class, 'store'])->name('narration-heads.store')->middleware('tenant.permission:narration_heads.create');
        Route::put('/narration-heads/{narration_head}', [NarrationHeadController::class, 'update'])->name('narration-heads.update')->middleware('tenant.permission:narration_heads.edit');
        Route::delete('/narration-heads/{narration_head}', [NarrationHeadController::class, 'destroy'])->name('narration-heads.destroy')->middleware(['tenant.permission:narration_heads.delete', 'no.impersonate']);

        Route::post('/narration-heads/{narration_head}/sub-heads', [NarrationHeadController::class, 'storeSubHead'])->name('narration-heads.sub-heads.store')->middleware('tenant.permission:narration_heads.create');
        Route::put('/narration-heads/{narration_head}/sub-heads/{narration_sub_head}', [NarrationHeadController::class, 'updateSubHead'])->name('narration-heads.sub-heads.update')->middleware('tenant.permission:narration_heads.edit');
        Route::delete('/narration-heads/{narration_head}/sub-heads/{narration_sub_head}', [NarrationHeadController::class, 'destroySubHead'])->name('narration-heads.sub-heads.destroy')->middleware(['tenant.permission:narration_heads.delete', 'no.impersonate']);

        // ── Accounting Assistant (Chat) ────────────────────────────────
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index')->middleware('tenant.permission:chat.view');
        Route::post('/chat', [ChatController::class, 'chat'])->name('chat.store')->middleware('tenant.permission:chat.view');

        // ── Invoices ───────────────────────────────────────────────────
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('tenant.permission:invoices.view');
        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create')->middleware('tenant.permission:invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store')->middleware('tenant.permission:invoices.create');
        Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit')->middleware('tenant.permission:invoices.edit');
        Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update')->middleware('tenant.permission:invoices.edit');
        Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy')->middleware(['tenant.permission:invoices.delete', 'no.impersonate']);
        Route::get('/invoices/{invoice}/download', [InvoiceController::class, 'download'])->name('invoices.download');

        // ── Tally Integration ─────────────────────────────────────────
        Route::get('/settings/tally', [TallyConnectionController::class, 'show'])->name('tally.connection.show')->middleware('tenant.permission:integrations.view');
        Route::post('/settings/tally', [TallyConnectionController::class, 'save'])->name('tally.connection.save')->middleware('tenant.permission:integrations.manage');
        Route::get('/settings/tally/test', [TallyConnectionController::class, 'testConnection'])->name('tally.connection.test')->middleware('tenant.permission:integrations.manage');
        Route::post('/settings/tally/regenerate-token', [TallyConnectionController::class, 'regenerateToken'])->name('tally.connection.regenerate-token')->middleware('tenant.permission:integrations.manage');
        Route::delete('/settings/tally', [TallyConnectionController::class, 'destroy'])->name('tally.connection.destroy')->middleware(['tenant.permission:integrations.manage', 'no.impersonate']);
        Route::get('/tally/sync', [TallySyncController::class, 'index'])->name('tally.sync.index')->middleware('tenant.permission:integrations.view');
        Route::post('/tally/sync', [TallySyncController::class, 'trigger'])->name('tally.sync.trigger')->middleware('tenant.permission:integrations.manage');
        Route::get('/tally/ledger-groups', [TallyDataController::class, 'ledgerGroups'])->name('tally.ledger-groups.index')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/ledgers', [TallyDataController::class, 'ledgers'])->name('tally.ledgers.index')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/stock-masters', [TallyDataController::class, 'stockMasters'])->name('tally.stock-masters.index')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/stock-items', [TallyDataController::class, 'stockItems'])->name('tally.stock-items.index')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/vouchers', [TallyDataController::class, 'vouchers'])->name('tally.vouchers.index')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/vouchers/{voucher}', [TallyDataController::class, 'voucherShow'])->name('tally.vouchers.show')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/statutory-masters', [TallyDataController::class, 'statutoryMasters'])->name('tally.statutory-masters.index')->middleware('tenant.permission:integrations.view');
        Route::get('/tally/payroll', [TallyDataController::class, 'payroll'])->name('tally.payroll.index')->middleware('tenant.permission:integrations.view');

        // ── Tally Master CRUD (manage permission required) ─────────────
        Route::middleware('tenant.permission:integrations.manage')->group(function () {
            // Ledger Groups
            Route::post('/tally/ledger-groups', [TallyMasterCrudController::class, 'ledgerGroupStore'])->name('tally.ledger-groups.store');
            Route::put('/tally/ledger-groups/{ledgerGroup}', [TallyMasterCrudController::class, 'ledgerGroupUpdate'])->name('tally.ledger-groups.update');
            Route::delete('/tally/ledger-groups/{ledgerGroup}', [TallyMasterCrudController::class, 'ledgerGroupDestroy'])->name('tally.ledger-groups.destroy');

            // Ledgers
            Route::post('/tally/ledgers', [TallyMasterCrudController::class, 'ledgerStore'])->name('tally.ledgers.store');
            Route::put('/tally/ledgers/{ledger}', [TallyMasterCrudController::class, 'ledgerUpdate'])->name('tally.ledgers.update');
            Route::delete('/tally/ledgers/{ledger}', [TallyMasterCrudController::class, 'ledgerDestroy'])->name('tally.ledgers.destroy');

            // Stock Groups
            Route::post('/tally/stock-groups', [TallyMasterCrudController::class, 'stockGroupStore'])->name('tally.stock-groups.store');
            Route::put('/tally/stock-groups/{stockGroup}', [TallyMasterCrudController::class, 'stockGroupUpdate'])->name('tally.stock-groups.update');
            Route::delete('/tally/stock-groups/{stockGroup}', [TallyMasterCrudController::class, 'stockGroupDestroy'])->name('tally.stock-groups.destroy');

            // Stock Categories
            Route::post('/tally/stock-categories', [TallyMasterCrudController::class, 'stockCategoryStore'])->name('tally.stock-categories.store');
            Route::put('/tally/stock-categories/{stockCategory}', [TallyMasterCrudController::class, 'stockCategoryUpdate'])->name('tally.stock-categories.update');
            Route::delete('/tally/stock-categories/{stockCategory}', [TallyMasterCrudController::class, 'stockCategoryDestroy'])->name('tally.stock-categories.destroy');

            // Stock Items
            Route::post('/tally/stock-items', [TallyMasterCrudController::class, 'stockItemStore'])->name('tally.stock-items.store');
            Route::put('/tally/stock-items/{stockItem}', [TallyMasterCrudController::class, 'stockItemUpdate'])->name('tally.stock-items.update');
            Route::delete('/tally/stock-items/{stockItem}', [TallyMasterCrudController::class, 'stockItemDestroy'])->name('tally.stock-items.destroy');

            // Statutory Masters
            Route::post('/tally/statutory-masters', [TallyMasterCrudController::class, 'statutoryMasterStore'])->name('tally.statutory-masters.store');
            Route::put('/tally/statutory-masters/{statutoryMaster}', [TallyMasterCrudController::class, 'statutoryMasterUpdate'])->name('tally.statutory-masters.update');
            Route::delete('/tally/statutory-masters/{statutoryMaster}', [TallyMasterCrudController::class, 'statutoryMasterDestroy'])->name('tally.statutory-masters.destroy');

            // Employee Groups
            Route::post('/tally/employee-groups', [TallyMasterCrudController::class, 'employeeGroupStore'])->name('tally.employee-groups.store');
            Route::put('/tally/employee-groups/{employeeGroup}', [TallyMasterCrudController::class, 'employeeGroupUpdate'])->name('tally.employee-groups.update');
            Route::delete('/tally/employee-groups/{employeeGroup}', [TallyMasterCrudController::class, 'employeeGroupDestroy'])->name('tally.employee-groups.destroy');

            // Employees
            Route::post('/tally/employees', [TallyMasterCrudController::class, 'employeeStore'])->name('tally.employees.store');
            Route::put('/tally/employees/{employee}', [TallyMasterCrudController::class, 'employeeUpdate'])->name('tally.employees.update');
            Route::delete('/tally/employees/{employee}', [TallyMasterCrudController::class, 'employeeDestroy'])->name('tally.employees.destroy');

            // Pay Heads
            Route::post('/tally/pay-heads', [TallyMasterCrudController::class, 'payHeadStore'])->name('tally.pay-heads.store');
            Route::put('/tally/pay-heads/{payHead}', [TallyMasterCrudController::class, 'payHeadUpdate'])->name('tally.pay-heads.update');
            Route::delete('/tally/pay-heads/{payHead}', [TallyMasterCrudController::class, 'payHeadDestroy'])->name('tally.pay-heads.destroy');

            // Attendance Types
            Route::post('/tally/attendance-types', [TallyMasterCrudController::class, 'attendanceTypeStore'])->name('tally.attendance-types.store');
            Route::put('/tally/attendance-types/{attendanceType}', [TallyMasterCrudController::class, 'attendanceTypeUpdate'])->name('tally.attendance-types.update');
            Route::delete('/tally/attendance-types/{attendanceType}', [TallyMasterCrudController::class, 'attendanceTypeDestroy'])->name('tally.attendance-types.destroy');

            // Vouchers
            Route::post('/tally/vouchers', [TallyVoucherCrudController::class, 'voucherStore'])->name('tally.vouchers.store');
            Route::put('/tally/vouchers/{voucher}', [TallyVoucherCrudController::class, 'voucherUpdate'])->name('tally.vouchers.update');
            Route::delete('/tally/vouchers/{voucher}', [TallyVoucherCrudController::class, 'voucherDestroy'])->name('tally.vouchers.destroy');
        });

        // ── Banking / Narration ────────────────────────────────────────
        Route::get('/banking', [BankTransactionController::class, 'pending'])
            ->name('banking.index')
            ->middleware('tenant.permission:transactions.view');

        // review route: transactions.review is the baseline; controller enforces
        // transactions.edit for the 'correct' action
        Route::post('/banking/transactions/{transaction}/review/{action}', [NarrationReviewController::class, 'handle'])
            ->name('banking.transactions.review')
            ->middleware('tenant.permission:transactions.review');

        Route::post('/banking/transactions/sms', SmsIngestController::class)
            ->name('banking.transactions.sms')
            ->middleware('tenant.permission:transactions.import');

        Route::post('/banking/transactions/email', EmailIngestController::class)
            ->name('banking.transactions.email')
            ->middleware('tenant.permission:transactions.import');

        Route::post('/banking/transactions/statement', StatementUploadController::class)
            ->name('banking.transactions.statement')
            ->middleware('tenant.permission:transactions.import');

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
