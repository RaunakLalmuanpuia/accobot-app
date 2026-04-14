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
                    ? Invoice::where('tenant_id', $transaction->tenant_id)->findOrFail($invoiceId)
                    : Invoice::where('tenant_id', $transaction->tenant_id)->where('invoice_number', $invoiceNumber)->firstOrFail();

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
                'note_template'         => $this->buildNoteTemplate($narrationNote, $transaction),
                'party_name'            => $transaction->party_name ?: null,
                'priority'              => 10,
                'is_active'             => true,
                'source'                => 'learned',
            ]
        );
    }

    /**
     * Convert a human-written note into a reusable template by replacing the
     * transaction's actual values with placeholders.
     *
     * e.g. "NEFT receipt from Suntech Solutions – ₹82,600.00 on 14-Apr-2026"
     *   →  "NEFT receipt from {party} – ₹{amount} on {date}"
     *
     * Next month, generateNote() fills the placeholders with the new values
     * so the note stays accurate without the accountant rewriting it each time.
     */
    private function buildNoteTemplate(?string $note, BankTransaction $transaction): ?string
    {
        if (!$note) {
            return null;
        }

        $replacements = [];

        // Amount variants — cover both "82,600.00" and "82600.00" forms
        $withCommas    = number_format((float) $transaction->amount, 2);
        $withoutCommas = number_format((float) $transaction->amount, 2, '.', '');
        foreach (array_unique([$withCommas, $withoutCommas]) as $formatted) {
            $replacements[$formatted]         = '{amount}';
            $replacements['₹' . $formatted]   = '₹{amount}';
            $replacements['Rs.' . $formatted]  = 'Rs.{amount}';
            $replacements['INR ' . $formatted] = 'INR {amount}';
        }

        // Party name
        if ($transaction->party_name) {
            $replacements[$transaction->party_name] = '{party}';
        }

        // Date variants
        if ($transaction->transaction_date) {
            $date = $transaction->transaction_date;
            $replacements[$date->format('d-M-Y')] = '{date}';
            $replacements[$date->format('d/m/Y')] = '{date}';
            $replacements[$date->format('d-m-Y')] = '{date}';
            $replacements[$date->format('Y-m-d')] = '{date}';
            $replacements[$date->format('d M Y')] = '{date}';
        }

        // Replace longest strings first to avoid partial replacements
        // e.g. "₹82,600.00" before "82,600.00"
        uksort($replacements, fn ($a, $b) => strlen($b) - strlen($a));

        return str_replace(array_keys($replacements), array_values($replacements), $note);
    }

    private function buildMatchValue(BankTransaction $transaction): string
    {
        // Best match key: party_name is the most stable identifier
        if (!empty($transaction->party_name)) {
            return strtolower(trim($transaction->party_name));
        }

        // Extract meaningful tokens from bank_reference (skip numeric/short tokens)
        if (!empty($transaction->bank_reference)) {
            $words = collect(preg_split('/[\s\/\-]+/', $transaction->bank_reference))
                ->filter(fn ($w) => strlen($w) >= 4 && !is_numeric($w))
                ->take(2)
                ->implode(' ');

            if (strlen($words) >= 4) {
                return strtolower($words);
            }
        }

        // Fall back to first 3 meaningful words of raw narration
        $words = collect(explode(' ', $transaction->raw_narration ?? ''))
            ->filter(fn ($w) => strlen($w) >= 4 && !is_numeric($w))
            ->take(3)
            ->implode(' ');

        return strtolower(trim($words ?: substr($transaction->raw_narration ?? '', 0, 30)));
    }
}
