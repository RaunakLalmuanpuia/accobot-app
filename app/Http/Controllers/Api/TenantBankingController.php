<?php

namespace App\Http\Controllers\Api;

use App\Actions\Banking\IngestEmailTransactionAction;
use App\Actions\Banking\IngestSmsTransactionAction;
use App\Actions\Banking\ProcessStatementAction;
use App\Actions\Banking\ReviewNarrationAction;
use App\Http\Controllers\Controller;
use App\Models\BankTransaction;
use App\Models\NarrationHead;
use App\Models\Tenant;
use App\Services\Banking\InvoiceMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TenantBankingController extends Controller
{
    public function __construct(
        private InvoiceMatchingService    $matcher,
        private ReviewNarrationAction     $reviewAction,
        private IngestSmsTransactionAction   $smsAction,
        private IngestEmailTransactionAction $emailAction,
        private ProcessStatementAction       $statementAction,
    ) {}

    // ── Narration Heads ────────────────────────────────────────────────────

    /**
     * GET /api/mobile/tenants/{tenant}/banking/narration-heads
     *
     * Returns all active narration heads with their sub-heads.
     * Used by the mobile correction UI to populate dropdowns.
     */
    public function narrationHeads(Tenant $tenant): JsonResponse
    {
        $heads = NarrationHead::with('activeSubHeads')
            ->where('tenant_id', $tenant->id)
            ->active()
            ->orderBy('sort_order')
            ->get()
            ->map(fn (NarrationHead $h) => [
                'id'        => $h->id,
                'name'      => $h->name,
                'slug'      => $h->slug,
                'type'      => $h->type,
                'sub_heads' => $h->activeSubHeads->map(fn ($s) => [
                    'id'             => $s->id,
                    'ledger_code'    => $s->ledger_code,
                    'ledger_name'    => $s->ledger_name,
                    'requires_party' => $s->requires_party,
                ]),
            ]);

        return response()->json(['heads' => $heads]);
    }

    // ── Pending Transactions ───────────────────────────────────────────────

    /**
     * GET /api/mobile/tenants/{tenant}/banking/pending
     *
     * Paginated list of pending/reviewed transactions for the tenant.
     * Query params: page (default 1), per_page (default 25, max 50)
     */
    public function pending(Request $request, Tenant $tenant): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 25), 50);

        $transactions = BankTransaction::with(['narrationHead', 'narrationSubHead', 'reconciledInvoice'])
            ->where('tenant_id', $tenant->id)
            ->where('is_duplicate', false)
            ->whereIn('review_status', ['pending', 'reviewed'])
            ->orderByDesc('transaction_date')
            ->paginate($perPage);

        $transactions->getCollection()->transform(function (BankTransaction $tx) {
            $tx->setAttribute(
                'invoice_suggestions',
                $tx->is_reconciled
                    ? []
                    : $this->formatSuggestions($this->matcher->findCandidates($tx))
            );
            return $tx;
        });

        return response()->json($transactions);
    }

    // ── Ingest ─────────────────────────────────────────────────────────────

    /**
     * POST /api/mobile/tenants/{tenant}/banking/ingest/sms
     *
     * Body:
     *   raw_sms           string  required  — raw SMS text from the bank (min 10, max 1000)
     *   bank_account_name string  optional  — label for the account (e.g. "HDFC Savings")
     */
    public function ingestSms(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'raw_sms'           => ['required', 'string', 'min:10', 'max:1000'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $this->smsAction->execute(
            $request->input('raw_sms'),
            $tenant,
            $request->input('bank_account_name', '')
        );

        return response()->json(['message' => 'SMS processed and transaction added for review.']);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/banking/ingest/email
     *
     * Body:
     *   email_body        string  required  — email body text (min 10, max 10000)
     *   email_subject     string  optional  — email subject line
     *   bank_account_name string  optional  — label for the account
     */
    public function ingestEmail(Request $request, Tenant $tenant): JsonResponse
    {
        $request->validate([
            'email_body'        => ['required', 'string', 'min:10', 'max:10000'],
            'email_subject'     => ['nullable', 'string', 'max:500'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $parts = [];
        if ($subject = trim($request->input('email_subject', ''))) {
            $parts[] = "Subject: {$subject}";
        }
        $parts[] = trim($request->input('email_body'));
        $rawEmail = implode("\n\n", $parts);

        $this->emailAction->execute(
            $rawEmail,
            $tenant,
            $request->input('bank_account_name', '')
        );

        return response()->json(['message' => 'Email processed and transaction added for review.']);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/banking/ingest/statement
     *
     * Multipart form-data:
     *   statement         file    required  — PDF, JPG, PNG, CSV, or XLSX (max 20 MB)
     *   bank_account_name string  optional  — label for the account
     *
     * Response includes import statistics.
     */
    public function ingestStatement(Request $request, Tenant $tenant): JsonResponse
    {
        set_time_limit(300);

        $request->validate([
            'statement'         => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,csv,xlsx'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $this->statementAction->execute(
            $request->file('statement'),
            $tenant,
            $request->input('bank_account_name', '')
        );

        $status = ($result['total'] > 0 && $result['imported'] === 0 && $result['duplicates'] === 0)
            ? 422
            : 200;

        return response()->json([
            'message'    => sprintf(
                'Statement processed: %d imported, %d duplicates skipped, %d failed out of %d total.',
                $result['imported'],
                $result['duplicates'],
                $result['failed'],
                $result['total']
            ),
            'stats' => $result,
        ], $status);
    }

    // ── Review Actions ─────────────────────────────────────────────────────

    /**
     * POST /api/mobile/tenants/{tenant}/banking/transactions/{transaction}/approve
     *
     * Marks the transaction as reviewed. Requires transactions.review permission.
     */
    public function approve(Request $request, Tenant $tenant, BankTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->tenant_id === $tenant->id, 404);
        abort_unless($request->user()->hasPermissionInTenant('transactions.review', $tenant), 403);

        $this->reviewAction->approve($transaction);

        return response()->json(['message' => 'Transaction approved.']);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/banking/transactions/{transaction}/correct
     *
     * Re-categorise a transaction and optionally save as a learned rule / reconcile.
     * Requires transactions.edit permission.
     *
     * Body:
     *   narration_head_id     int     required
     *   narration_sub_head_id int     optional
     *   narration_note        string  optional  (max 500)
     *   party_name            string  optional  (max 255)
     *   save_as_rule          bool    optional  (default false)
     *   invoice_id            int     optional  — reconcile to this invoice
     *   invoice_number        string  optional  — reconcile by invoice number
     *   unreconcile           bool    optional  — remove existing reconciliation
     */
    public function correct(Request $request, Tenant $tenant, BankTransaction $transaction): JsonResponse
    {
        abort_unless($transaction->tenant_id === $tenant->id, 404);
        abort_unless($request->user()->hasPermissionInTenant('transactions.edit', $tenant), 403);

        $request->validate([
            'narration_head_id'     => ['required', 'integer', 'exists:narration_heads,id'],
            'narration_sub_head_id' => ['nullable', 'integer', 'exists:narration_sub_heads,id'],
            'party_name'            => ['nullable', 'string', 'max:255'],
            'narration_note'        => ['nullable', 'string', 'max:500'],
            'save_as_rule'          => ['boolean'],
            'invoice_id'            => ['nullable', 'integer', 'exists:invoices,id'],
            'invoice_number'        => ['nullable', 'string', 'max:100'],
            'unreconcile'           => ['boolean'],
        ]);

        $this->reviewAction->correct(
            transaction:        $transaction,
            narrationHeadId:    (int) $request->input('narration_head_id'),
            narrationSubHeadId: $request->input('narration_sub_head_id') ? (int) $request->input('narration_sub_head_id') : null,
            narrationNote:      $request->input('narration_note'),
            partyName:          $request->input('party_name'),
            saveAsRule:         (bool) $request->input('save_as_rule', false),
            invoiceId:          $request->input('invoice_id') ? (int) $request->input('invoice_id') : null,
            invoiceNumber:      $request->input('invoice_number'),
            unreconcile:        (bool) $request->input('unreconcile', false),
        );

        return response()->json(['message' => 'Transaction corrected.']);
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function formatSuggestions(Collection $scored): array
    {
        return $scored->take(3)->map(fn (array $r) => [
            'id'             => $r['invoice']->id,
            'invoice_number' => $r['invoice']->invoice_number,
            'client_name'    => $r['invoice']->client_name,
            'amount_due'     => (float) $r['invoice']->amount_due,
            'total_amount'   => (float) $r['invoice']->total_amount,
            'invoice_date'   => $r['invoice']->invoice_date->toDateString(),
            'status'         => $r['invoice']->status,
            'match_score'    => $r['match_score'],
            'match_reasons'  => $r['match_reasons'],
        ])->values()->all();
    }
}
