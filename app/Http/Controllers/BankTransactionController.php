<?php

namespace App\Http\Controllers;

use App\Models\BankTransaction;
use App\Models\NarrationHead;
use App\Models\Tenant;
use App\Services\Banking\InvoiceMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class BankTransactionController extends Controller
{
    public function __construct(private InvoiceMatchingService $matcher) {}

    public function pending(Request $request, Tenant $tenant): Response
    {
        $transactions = BankTransaction::with(['narrationHead', 'narrationSubHead', 'reconciledInvoice'])
            ->where('is_duplicate', false)
            ->whereIn('review_status', ['pending', 'reviewed'])
            ->orderByDesc('transaction_date')
            ->paginate(25);

        $transactions->getCollection()->transform(function (BankTransaction $tx) {
            $tx->setAttribute(
                'invoice_suggestions',
                $tx->is_reconciled
                    ? []
                    : $this->formatSuggestions($this->matcher->findCandidates($tx))
            );
            return $tx;
        });

        $heads = NarrationHead::with('activeSubHeads')
            ->active()
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Banking/PendingReviews', [
            'transactions' => $transactions,
            'heads'        => $heads,
        ]);
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
