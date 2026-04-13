<?php

namespace App\Http\Controllers;

use App\Actions\Banking\ReviewNarrationAction;
use App\Http\Requests\Banking\NarrationReviewRequest;
use App\Models\BankTransaction;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NarrationReviewController extends Controller
{
    public function __construct(private ReviewNarrationAction $action) {}

    public function handle(
        NarrationReviewRequest $request,
        Tenant                 $tenant,
        BankTransaction        $transaction,
        string                 $action,
    ): RedirectResponse {
        // 'correct' (full re-categorization) requires the stronger permission
        if ($action === 'correct') {
            abort_unless($request->user()->hasPermissionInTenant('transactions.edit', $tenant), 403);
        }

        match ($action) {
            'approve' => $this->action->approve($transaction),

            'correct' => $this->action->correct(
                transaction:        $transaction,
                narrationHeadId:    (int) $request->narration_head_id,
                narrationSubHeadId: $request->narration_sub_head_id ? (int) $request->narration_sub_head_id : null,
                narrationNote:      $request->narration_note,
                partyName:          $request->party_name,
                saveAsRule:         (bool) $request->input('save_as_rule', false),
                invoiceId:          $request->invoice_id   ? (int) $request->invoice_id : null,
                invoiceNumber:      $request->invoice_number,
                unreconcile:        (bool) $request->input('unreconcile', false),
            ),

            'reject'  => $this->action->reject($transaction),

            default   => abort(422, 'Invalid action.'),
        };

        return back()->with('success', "Transaction {$action}d successfully.");
    }
}
