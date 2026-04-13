<?php

namespace App\Actions\Banking;

use App\Models\BankTransaction;
use App\Models\Invoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconcileTransactionAction
{
    /**
     * Link a bank transaction to an invoice and record the payment.
     */
    public function execute(BankTransaction $transaction, Invoice $invoice): BankTransaction
    {
        if ($transaction->is_reconciled) {
            throw ValidationException::withMessages([
                'invoice' => 'This transaction has already been reconciled.',
            ]);
        }

        if ($invoice->tenant_id !== $transaction->tenant_id) {
            throw ValidationException::withMessages([
                'invoice' => 'Invoice does not belong to this tenant.',
            ]);
        }

        if ((float) $invoice->amount_due <= 0) {
            throw ValidationException::withMessages([
                'invoice' => "Invoice {$invoice->invoice_number} is already fully paid.",
            ]);
        }

        return DB::transaction(function () use ($transaction, $invoice) {
            $transaction->update([
                'is_reconciled'         => true,
                'reconciled_invoice_id' => $invoice->id,
                'reconciled_at'         => now()->toDateString(),
            ]);

            $invoice->recordPayment((float) $transaction->amount);

            return $transaction->fresh(['narrationHead', 'narrationSubHead', 'reconciledInvoice']);
        });
    }

    /**
     * Undo a reconciliation — unlinks the transaction and reverses the invoice payment.
     */
    public function unreconcile(BankTransaction $transaction): BankTransaction
    {
        if (!$transaction->is_reconciled || !$transaction->reconciled_invoice_id) {
            throw ValidationException::withMessages([
                'invoice' => 'This transaction is not currently reconciled.',
            ]);
        }

        return DB::transaction(function () use ($transaction) {
            $invoice = $transaction->reconciledInvoice;

            if ($invoice) {
                $reversedPaid = max(0.0, (float) $invoice->amount_paid - (float) $transaction->amount);
                $invoice->update([
                    'amount_paid' => $reversedPaid,
                    'amount_due'  => (float) $invoice->total - $reversedPaid,
                    'status'      => $reversedPaid > 0 ? 'partial' : 'sent',
                ]);
            }

            $transaction->update([
                'is_reconciled'         => false,
                'reconciled_invoice_id' => null,
                'reconciled_at'         => null,
            ]);

            return $transaction->fresh(['narrationHead', 'narrationSubHead']);
        });
    }
}
