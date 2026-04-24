<?php

use App\Http\Controllers\Api\MobileAuthController;
use App\Http\Controllers\Api\Tally\TallyConfirmController;
use App\Http\Controllers\Api\Tally\TallyInboundMastersController;
use App\Http\Controllers\Api\Tally\TallyInboundPayrollController;
use App\Http\Controllers\Api\Tally\TallyInboundReportsController;
use App\Http\Controllers\Api\Tally\TallyInboundVouchersController;
use App\Http\Controllers\Api\Tally\TallyOutboundController;
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
        // GET   /api/mobile/tenants/{tenant}/banking/reviewed
        // GET   /api/mobile/tenants/{tenant}/banking/narration-heads
        Route::prefix('banking')->name('banking.')->middleware('tenant.permission:transactions.view')->group(function () {
            Route::get('pending',         [TenantBankingController::class, 'pending'])->name('pending');
            Route::get('reviewed',        [TenantBankingController::class, 'reviewed'])->name('reviewed');
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

// ── Tally: Inbound (connector → Accobot) ──────────────────────────────────
Route::prefix('tally/inbound')->middleware('throttle:120,1')->group(function () {
    Route::post('masters/ledger-groups',    [TallyInboundMastersController::class, 'ledgerGroups']);
    Route::post('masters/ledgers',          [TallyInboundMastersController::class, 'ledgers']);
    Route::post('masters/stock-items',      [TallyInboundMastersController::class, 'stockItems']);
    Route::post('masters/stock-groups',     [TallyInboundMastersController::class, 'stockGroups']);
    Route::post('masters/stock-categories', [TallyInboundMastersController::class, 'stockCategories']);
    Route::post('masters/godowns',          [TallyInboundMastersController::class, 'godowns']);
    Route::post('masters/statutory',        [TallyInboundMastersController::class, 'statutory']);
    Route::post('payroll/employee-groups',  [TallyInboundPayrollController::class, 'employeeGroups']);
    Route::post('payroll/employees',        [TallyInboundPayrollController::class, 'employees']);
    Route::post('payroll/pay-heads',        [TallyInboundPayrollController::class, 'payHeads']);
    Route::post('payroll/attendance-types', [TallyInboundPayrollController::class, 'attendanceTypes']);
    Route::post('payroll/salary-voucher',   [TallyInboundVouchersController::class, 'salary']);
    Route::post('payroll/attendance-voucher', [TallyInboundVouchersController::class, 'attendance']);
    Route::post('vouchers/sales',           [TallyInboundVouchersController::class, 'sales']);
    Route::post('vouchers/credit-note',     [TallyInboundVouchersController::class, 'creditNote']);
    Route::post('vouchers/purchase',        [TallyInboundVouchersController::class, 'purchase']);
    Route::post('vouchers/debit-note',      [TallyInboundVouchersController::class, 'debitNote']);
    Route::post('vouchers/receipt',         [TallyInboundVouchersController::class, 'receipt']);
    Route::post('vouchers/payment',         [TallyInboundVouchersController::class, 'payment']);
    Route::post('vouchers/contra',          [TallyInboundVouchersController::class, 'contra']);
    Route::post('vouchers/journal',         [TallyInboundVouchersController::class, 'journal']);
    Route::post('reports/balance-sheet',    [TallyInboundReportsController::class, 'balanceSheet']);
    Route::post('reports/profit-loss',      [TallyInboundReportsController::class, 'profitLoss']);
    Route::post('reports/cash-flow',        [TallyInboundReportsController::class, 'cashFlow']);
    Route::post('reports/ratio-analysis',   [TallyInboundReportsController::class, 'ratioAnalysis']);
});

// ── Tally: Outbound GET (exact Swagger paths — Tally reads Accobot data) ──
Route::prefix('MastersAPI')->middleware('throttle:120,1')->group(function () {
    Route::get('ledger-group',    [TallyOutboundController::class, 'ledgerGroup']);
    Route::get('ledger-master',   [TallyOutboundController::class, 'ledgerMaster']);
    Route::get('stock-master',    [TallyOutboundController::class, 'stockMaster']);
    Route::get('stock-group',     [TallyOutboundController::class, 'stockGroup']);
    Route::get('stock-category',  [TallyOutboundController::class, 'stockCategory']);
    Route::post('update-ledger-master',   [TallyConfirmController::class, 'ledgerMaster']);
    Route::post('update-stock-master',    [TallyConfirmController::class, 'stockMaster']);
    Route::post('update-ledger-group',    [TallyConfirmController::class, 'ledgerGroup']);
    Route::post('update-stock-group',     [TallyConfirmController::class, 'stockGroup']);
    Route::post('update-stock-category',  [TallyConfirmController::class, 'stockCategory']);
    Route::get('statutory-master',        [TallyOutboundController::class, 'statutoryMaster']);
    Route::post('update-statutory-master', [TallyConfirmController::class, 'statutoryMaster']);
});

Route::prefix('PayrollAPI')->middleware('throttle:120,1')->group(function () {
    Route::get('employee-group',    [TallyOutboundController::class, 'employeeGroup']);
    Route::get('employee',          [TallyOutboundController::class, 'employee']);
    Route::get('pay-head',          [TallyOutboundController::class, 'payHead']);
    Route::get('attendance-type',   [TallyOutboundController::class, 'attendanceType']);
    Route::get('salary-voucher',    [TallyOutboundController::class, 'salaryVoucher']);
    Route::get('attendance-voucher', [TallyOutboundController::class, 'attendanceVoucher']);
    Route::post('update-employee-group',    [TallyConfirmController::class, 'employeeGroup']);
    Route::post('update-employee',          [TallyConfirmController::class, 'employee']);
    Route::post('update-pay-head',          [TallyConfirmController::class, 'payHead']);
    Route::post('update-attendance-type',   [TallyConfirmController::class, 'attendanceType']);
    Route::post('update-salary-voucher',    [TallyConfirmController::class, 'salaryVoucher']);
    Route::post('update-attendance-voucher', [TallyConfirmController::class, 'attendanceVoucher']);
});

Route::prefix('VoucherAPI')->middleware('throttle:120,1')->group(function () {
    Route::get('sales-voucher',      [TallyOutboundController::class, 'salesVoucher']);
    Route::get('purchase-voucher',   [TallyOutboundController::class, 'purchaseVoucher']);
    Route::get('debitNote-voucher',  [TallyOutboundController::class, 'debitNoteVoucher']);
    Route::get('creditNote-voucher', [TallyOutboundController::class, 'creditNoteVoucher']);
    Route::get('receipt-voucher',    [TallyOutboundController::class, 'receiptVoucher']);
    Route::get('payment-voucher',    [TallyOutboundController::class, 'paymentVoucher']);
    Route::get('contra-voucher',     [TallyOutboundController::class, 'contraVoucher']);
    Route::get('journal-voucher',    [TallyOutboundController::class, 'journalVoucher']);
    Route::post('update-sales-voucher',      [TallyConfirmController::class, 'salesVoucher']);
    Route::post('update-purchase-voucher',   [TallyConfirmController::class, 'purchaseVoucher']);
    Route::post('update-debitnote-voucher',  [TallyConfirmController::class, 'debitNoteVoucher']);
    Route::post('update-creditnote-voucher', [TallyConfirmController::class, 'creditNoteVoucher']);
    Route::post('update-receipt-voucher',    [TallyConfirmController::class, 'receiptVoucher']);
    Route::post('update-payment-voucher',    [TallyConfirmController::class, 'paymentVoucher']);
    Route::post('update-contra-voucher',     [TallyConfirmController::class, 'contraVoucher']);
    Route::post('update-journal-voucher',    [TallyConfirmController::class, 'journalVoucher']);
});
