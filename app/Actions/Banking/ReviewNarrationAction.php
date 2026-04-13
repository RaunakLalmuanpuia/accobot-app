<?php

namespace App\Actions\Banking;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\NarrationHead;
use App\Models\NarrationRule;
use App\Models\NarrationSubHead;
use Illuminate\Support\Facades\DB;

class ReviewNarrationAction
{
    public function __construct(private ReconcileTransactionAction $reconciler) {}

    public function approve(BankTransaction $transaction): BankTransaction
    {
        $transaction->update(['review_status' => 'reviewed']);
        return $transaction->fresh(['narrationHead', 'narrationSubHead']);
    }

    public function correct(
        BankTransaction $transaction,
        int             $narrationHeadId,
        ?int            $narrationSubHeadId = null,
        ?string         $narrationNote      = null,
        ?string         $partyName          = null,
        bool            $saveAsRule         = false,
        ?int            $invoiceId          = null,
        ?string         $invoiceNumber      = null,
        bool            $unreconcile        = false,
    ): BankTransaction {
        return DB::transaction(function () use (
            $transaction, $narrationHeadId, $narrationSubHeadId,
            $narrationNote, $partyName, $saveAsRule,
            $invoiceId, $invoiceNumber, $unreconcile,
        ) {
            // 1. Update narration
            $transaction->update([
                'narration_head_id'     => $narrationHeadId,
                'narration_sub_head_id' => $narrationSubHeadId,
                'narration_note'        => $narrationNote,
                'party_name'            => $partyName ?? $transaction->party_name,
                'narration_source'      => 'manual',
                'review_status'         => 'reviewed',
            ]);

            // 2. Handle reconciliation change
            if ($unreconcile && $transaction->is_reconciled) {
                $this->reconciler->unreconcile($transaction);
            } elseif ($invoiceId || $invoiceNumber) {
                $invoice = $invoiceId
                    ? Invoice::findOrFail($invoiceId)
                    : Invoice::where('invoice_number', $invoiceNumber)->firstOrFail();

                if (!$transaction->is_reconciled || $transaction->reconciled_invoice_id !== $invoice->id) {
                    if ($transaction->is_reconciled) {
                        $this->reconciler->unreconcile($transaction);
                        $transaction->refresh();
                    }
                    $this->reconciler->execute($transaction, $invoice);
                }
            }

            // 3. Optionally save a learning rule
            if ($saveAsRule && strlen((string) $transaction->raw_narration) >= 4) {
                $this->createLearningRule($transaction, $narrationHeadId, $narrationSubHeadId, $narrationNote);
            }

            return $transaction->fresh(['narrationHead', 'narrationSubHead', 'reconciledInvoice']);
        });
    }

    public function reject(BankTransaction $transaction): BankTransaction
    {
        $transaction->update([
            'review_status'         => 'flagged',
            'narration_head_id'     => null,
            'narration_sub_head_id' => null,
            'narration_note'        => null,
        ]);
        return $transaction->fresh();
    }

    private function createLearningRule(
        BankTransaction $transaction,
        int             $headId,
        ?int            $subHeadId,
        ?string         $narrationNote,
    ): NarrationRule {
        $matchValue = $this->buildMatchValue($transaction);

        return NarrationRule::updateOrCreate(
            [
                'tenant_id'        => $transaction->tenant_id,
                'match_value'      => $matchValue,
                'match_type'       => 'contains',
                'transaction_type' => $transaction->type,
            ],
            [
                'narration_head_id'     => $headId,
                'narration_sub_head_id' => $subHeadId,
                'note_template'         => $narrationNote,
                'priority'              => 10,
                'is_active'             => true,
                'source'                => 'learned',
            ]
        );
    }

    private function buildMatchValue(BankTransaction $transaction): string
    {
        if (!empty($transaction->party_name)) {
            return strtolower(trim($transaction->party_name));
        }

        if (!empty($transaction->bank_reference)) {
            return strtolower(trim(substr($transaction->bank_reference, 0, 20)));
        }

        return strtolower(trim(substr($transaction->raw_narration ?? '', 0, 30)));
    }
}
