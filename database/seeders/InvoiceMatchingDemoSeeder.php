<?php

namespace Database\Seeders;

use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\NarrationSubHead;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds invoice + transaction pairs that demonstrate every scoring branch of
 * InvoiceMatchingService:
 *
 * Pair A  — Perfect score  : exact amount + exact name + invoice# in bank ref
 * Pair B  — Strong match   : exact amount + exact name + date within 3 days
 * Pair C  — Strong match   : exact amount + partial name + date within 3 days
 * Pair D  — Possible match : exact amount + no name match + date close
 * Pair E  — TDS / deduction: amount 10% short + exact name (possible match)
 * Pair F  — Already reconciled: invoice paid, transaction shows reconciled state
 * Pair G  — Partial payment: invoice half-paid; second tx targets remaining amount_due
 *
 * All invoice numbers are prefixed "INV-DEMO-" to avoid collisions with
 * auto-generated numbers. Idempotent — safe to re-run.
 */
class InvoiceMatchingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found — skipping InvoiceMatchingDemoSeeder.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }

        $this->command->info('Invoice matching demo data seeded for all tenants.');
    }

    // ── Private ────────────────────────────────────────────────────────────

    private function seedForTenant(Tenant $tenant): void
    {
        // Resolve clients seeded by AccountingSeeder (and ClientSeeder)
        $clients = Client::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->get()
            ->keyBy('name');

        $suntech = $clients->get('Suntech Solutions Pvt Ltd');
        $rajan   = $clients->get('Rajan Enterprises');
        $priya   = $clients->get('Priya Mehta');
        $ananya  = $clients->get('Ananya Singh');

        if (! $suntech || ! $rajan || ! $priya || ! $ananya) {
            $this->command->warn("  Tenant [{$tenant->name}]: required clients not found — run AccountingSeeder first.");
            return;
        }

        // Narration sub-heads (for seeded transactions)
        $sub = fn (string $slug) => NarrationSubHead::withoutGlobalScope('tenant')
            ->whereHas('narrationHead', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->where('slug', $slug)
            ->first();

        // ── PAIR A — Perfect score ─────────────────────────────────────────
        // Invoice# appears in bank_reference → +25 bonus on top of exact amount + exact name.
        // Expected score: 50 (amount) + 30 (name exact) + 15 (0 days) + 25 (ref) = 120

        $invA = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-001',
            'client_id'      => $suntech->id,
            'issue_date'     => '2026-04-08',
            'due_date'       => '2026-04-22',
            'status'         => 'sent',
            'subtotal'       => 70000.00,
            'tax_amount'     => 12600.00,
            'total'          => 82600.00,
            'amount_paid'    => 0,
            'amount_due'     => 82600.00,
            'currency'       => 'INR',
            'notes'          => 'Software consulting for Q1 2026',
        ], [
            ['description' => 'Software Consulting', 'unit' => 'hour', 'quantity' => 28, 'unit_price' => 2500.00, 'tax_rate' => 18],
        ]);

        $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-08',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'NEFT/26040801001/INV-DEMO-001',   // invoice# embedded
            'raw_narration'     => 'NEFT CR-SUNTECH SOLUTIONS PVT LTD-INV-DEMO-001-KOTAK BANK',
            'type'              => 'credit',
            'amount'            => 82600.00,
            'balance_after'     => 387383.00,
            'party_name'        => 'Suntech Solutions Pvt Ltd',        // exact client name
            'narration_note'    => 'Software consulting payment received',
            'sub_head_slug'     => 'service_fees',
            'narration_source'  => 'ai_suggested',
            'review_status'     => 'pending',
            'ai_confidence'     => 0.92,
            'ai_suggestions'    => [
                ['head' => 'Revenue', 'sub_head' => 'Service Fees', 'confidence' => 0.92, 'reasoning' => 'NEFT credit from Suntech Solutions with invoice reference.'],
            ],
        ]);

        // ── PAIR B — Strong match (no ref, but exact amount + exact name + 0 days) ──
        // Expected score: 50 + 30 + 15 = 95

        $invB = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-002',
            'client_id'      => $rajan->id,
            'issue_date'     => '2026-04-11',
            'due_date'       => '2026-04-25',
            'status'         => 'sent',
            'subtotal'       => 70000.00,
            'tax_amount'     => 12600.00,
            'total'          => 82600.00,
            'amount_paid'    => 0,
            'amount_due'     => 82600.00,
            'currency'       => 'INR',
            'notes'          => 'LED TV batch order — April',
        ], [
            ['description' => 'Brand A - 19" LED TV', 'unit' => 'piece', 'quantity' => 5, 'unit_price' => 12500.00, 'tax_rate' => 28],
            ['description' => 'Brand B - 19" LED TV', 'unit' => 'piece', 'quantity' => 2, 'unit_price' => 9500.00,  'tax_rate' => 28],
        ]);

        $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-11',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'NEFT/26041101002/RAJAN',          // no invoice# in ref
            'raw_narration'     => 'NEFT CR-RAJAN ENTERPRISES-PAYMENT-ICICI BANK',
            'type'              => 'credit',
            'amount'            => 82600.00,
            'balance_after'     => 304783.00,
            'party_name'        => 'Rajan Enterprises',               // exact match
            'narration_note'    => 'Payment received from Rajan Enterprises',
            'sub_head_slug'     => 'product_sales',
            'narration_source'  => 'rule_based',
            'review_status'     => 'pending',
            'ai_confidence'     => 1.00,
            'ai_suggestions'    => null,
        ]);

        // ── PAIR C — Strong match (partial name — "Priya Mehta" vs "Mehta Consulting") ──
        // Party name in tx is "Mehta Consulting" (company name), client name is "Priya Mehta"
        // str_contains check fails but similar_text ~70% → +10
        // Expected score: 50 + 10 (similar name) + 10 (2 days) = 70

        $invC = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-003',
            'client_id'      => $priya->id,
            'issue_date'     => '2026-04-09',
            'due_date'       => '2026-04-23',
            'status'         => 'sent',
            'subtotal'       => 50000.00,
            'tax_amount'     => 9000.00,
            'total'          => 59000.00,
            'amount_paid'    => 0,
            'amount_due'     => 59000.00,
            'currency'       => 'INR',
            'notes'          => 'Web design project — Phase 2',
        ], [
            ['description' => 'Web Design Service', 'unit' => 'hour', 'quantity' => 33, 'unit_price' => 1500.00, 'tax_rate' => 18],
            ['description' => 'UI Review & Revisions', 'unit' => 'hour', 'quantity' => 1, 'unit_price' => 1400.00, 'tax_rate' => 18],
        ]);

        $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-11',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'NEFT/26041101003/MEHTA',
            'raw_narration'     => 'NEFT CR-MEHTA CONSULTING-APR2026-HDFC BANK',
            'type'              => 'credit',
            'amount'            => 59000.00,
            'balance_after'     => 245783.00,
            'party_name'        => 'Mehta Consulting',                 // company name, not client name
            'narration_note'    => null,
            'sub_head_slug'     => null,
            'narration_source'  => 'manual',
            'review_status'     => 'pending',
            'ai_confidence'     => null,
            'ai_suggestions'    => null,
        ]);

        // ── PAIR D — Possible match (exact amount, no party name on tx) ──────
        // No party name → no name score. Only amount + date.
        // Expected score: 50 + 10 (within a week) = 60  → still Possible

        $invD = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-004',
            'client_id'      => $ananya->id,
            'issue_date'     => '2026-04-05',
            'due_date'       => '2026-04-19',
            'status'         => 'sent',
            'subtotal'       => 36000.00,
            'tax_amount'     => 6480.00,
            'total'          => 42480.00,
            'amount_paid'    => 0,
            'amount_due'     => 42480.00,
            'currency'       => 'INR',
            'notes'          => 'Annual Maintenance Plan — 2026',
        ], [
            ['description' => 'Annual Maintenance Plan', 'unit' => 'year', 'quantity' => 1, 'unit_price' => 36000.00, 'tax_rate' => 18],
        ]);

        $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-12',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'IMPS/26041201004/ADESIGN',
            'raw_narration'     => 'IMPS CR-UNKNOWN SENDER-MAINTENANCE-AXIS BANK',
            'type'              => 'credit',
            'amount'            => 42480.00,
            'balance_after'     => 203303.00,
            'party_name'        => null,                              // no party — no name score
            'narration_note'    => null,
            'sub_head_slug'     => null,
            'narration_source'  => 'manual',
            'review_status'     => 'pending',
            'ai_confidence'     => null,
            'ai_suggestions'    => null,
        ]);

        // ── PAIR E — TDS deduction (10% short) ────────────────────────────
        // Invoice ₹1,00,000. Tx ₹90,000 (₹10,000 TDS deducted = exactly 10%).
        // Expected score: 15 (amount within 10%) + 30 (exact name) + 5 (date within month) = 50 → Possible

        $invE = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-005',
            'client_id'      => $rajan->id,
            'issue_date'     => '2026-03-28',
            'due_date'       => '2026-04-11',
            'status'         => 'overdue',
            'subtotal'       => 84746.00,
            'tax_amount'     => 15254.00,
            'total'          => 100000.00,
            'amount_paid'    => 0,
            'amount_due'     => 100000.00,
            'currency'       => 'INR',
            'notes'          => 'Q1 bulk TV order — delayed payment',
        ], [
            ['description' => 'Brand A - 19" LED TV', 'unit' => 'piece', 'quantity' => 4, 'unit_price' => 12500.00, 'tax_rate' => 28],
            ['description' => 'Brand B - 17" Smart TV', 'unit' => 'piece', 'quantity' => 4, 'unit_price' => 11000.00, 'tax_rate' => 28],
        ]);

        $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-13',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'NEFT/26041301005/RAJAN',
            'raw_narration'     => 'NEFT CR-RAJAN ENTERPRISES-TDS NET-ICICI BANK',
            'type'              => 'credit',
            'amount'            => 90000.00,                          // ₹10,000 TDS short
            'balance_after'     => 293303.00,
            'party_name'        => 'Rajan Enterprises',
            'narration_note'    => null,
            'sub_head_slug'     => null,
            'narration_source'  => 'manual',
            'review_status'     => 'pending',
            'ai_confidence'     => null,
            'ai_suggestions'    => null,
        ]);

        // ── PAIR F — Already reconciled ────────────────────────────────────
        // Invoice fully paid, transaction linked. Demonstrates reconciled UI state.

        $invF = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-006',
            'client_id'      => $priya->id,
            'issue_date'     => '2026-04-02',
            'due_date'       => '2026-04-16',
            'status'         => 'paid',
            'subtotal'       => 12712.00,
            'tax_amount'     => 2288.00,
            'total'          => 15000.00,
            'amount_paid'    => 15000.00,
            'amount_due'     => 0.00,
            'currency'       => 'INR',
            'notes'          => 'Logo design & brand kit',
        ], [
            ['description' => 'Web Design Service', 'unit' => 'hour', 'quantity' => 8, 'unit_price' => 1500.00, 'tax_rate' => 18],
            ['description' => 'Brand Asset Export', 'unit' => 'hour', 'quantity' => 0.5, 'unit_price' => 1400.00, 'tax_rate' => 18],
        ]);

        $txF = $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-04',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'UPI/26040401006/PRIYA',
            'raw_narration'     => 'UPI CR-PRIYA MEHTA-INV-DEMO-006-HDFC BANK',
            'type'              => 'credit',
            'amount'            => 15000.00,
            'balance_after'     => 370710.00,
            'party_name'        => 'Priya Mehta',
            'narration_note'    => 'Logo design payment received',
            'sub_head_slug'     => 'service_fees',
            'narration_source'  => 'manual',
            'review_status'     => 'reviewed',
            'ai_confidence'     => null,
            'ai_suggestions'    => null,
        ]);

        // Link the reconciliation directly (bypass the guard that checks is_reconciled)
        if (! $txF->is_reconciled) {
            $txF->update([
                'is_reconciled'         => true,
                'reconciled_invoice_id' => $invF->id,
                'reconciled_at'         => '2026-04-04',
            ]);
        }

        // ── PAIR G — Partial payment ───────────────────────────────────────
        // Invoice ₹1,20,000. First payment ₹60,000 already reconciled (invoice partial).
        // Second tx ₹60,000 pending → should match remaining amount_due exactly.
        // Expected score: 50 (exact amount_due) + 20 (partial name) + 15 (0 days) = 85 → Strong

        $invG = $this->upsertInvoice($tenant, [
            'invoice_number' => 'INV-DEMO-007',
            'client_id'      => $ananya->id,
            'issue_date'     => '2026-04-07',
            'due_date'       => '2026-04-21',
            'status'         => 'partial',
            'subtotal'       => 1016949.00 / 10,   // avoid float weirdness — set below
            'tax_amount'     => 0,
            'total'          => 120000.00,
            'amount_paid'    => 60000.00,
            'amount_due'     => 60000.00,
            'currency'       => 'INR',
            'notes'          => 'Design Studio — website redesign, split payment',
        ], [
            ['description' => 'Web Design Service — Full Project', 'unit' => 'hour', 'quantity' => 40, 'unit_price' => 2500.00, 'tax_rate' => 20],
        ]);

        // Fix the subtotal/tax we can't easily pre-compute
        $invG->update(['subtotal' => 100000.00, 'tax_amount' => 20000.00]);

        // First payment tx — already reconciled
        $txG1 = $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-07',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'NEFT/26040701007A/ANANYA',
            'raw_narration'     => 'NEFT CR-ANANYA SINGH DESIGN STUDIO-PART1-AXIS BANK',
            'type'              => 'credit',
            'amount'            => 60000.00,
            'balance_after'     => 394100.00,
            'party_name'        => 'Ananya Singh Design Studio',
            'narration_note'    => 'Partial payment — website project',
            'sub_head_slug'     => 'service_fees',
            'narration_source'  => 'manual',
            'review_status'     => 'reviewed',
            'ai_confidence'     => null,
            'ai_suggestions'    => null,
        ]);

        if (! $txG1->is_reconciled) {
            $txG1->update([
                'is_reconciled'         => true,
                'reconciled_invoice_id' => $invG->id,
                'reconciled_at'         => '2026-04-07',
            ]);
        }

        // Second payment tx — pending, will match the remaining ₹60,000 amount_due
        $this->upsertTransaction($tenant, [
            'transaction_date'  => '2026-04-13',
            'bank_account_name' => 'HDFC Current Account',
            'bank_reference'    => 'NEFT/26041301007B/ANANYA',
            'raw_narration'     => 'NEFT CR-ANANYA SINGH DESIGN STUDIO-PART2-AXIS BANK',
            'type'              => 'credit',
            'amount'            => 60000.00,                          // matches amount_due exactly
            'balance_after'     => 353303.00,
            'party_name'        => 'Ananya Singh Design Studio',     // partial match vs "Ananya Singh"
            'narration_note'    => null,
            'sub_head_slug'     => null,
            'narration_source'  => 'manual',
            'review_status'     => 'pending',
            'ai_confidence'     => null,
            'ai_suggestions'    => null,
        ]);

        $this->command->line("  [{$tenant->name}] 7 invoice pairs seeded.");
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Idempotent invoice create/update by invoice_number + tenant.
     * @param  array<array{description:string,unit:string,quantity:float,unit_price:float,tax_rate:float}>  $items
     */
    private function upsertInvoice(Tenant $tenant, array $attrs, array $items): Invoice
    {
        // After migration 2026_04_14_000004 the unique constraint is (tenant_id, invoice_number),
        // so the same demo invoice number is safe to use across different tenants.
        $invoice = Invoice::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $tenant->id, 'invoice_number' => $attrs['invoice_number']],
            array_merge($attrs, ['tenant_id' => $tenant->id])
        );

        // Re-sync items only when freshly created
        if ($invoice->wasRecentlyCreated) {
            foreach ($items as $item) {
                $qty      = $item['quantity'];
                $price    = $item['unit_price'];
                $taxRate  = $item['tax_rate'];
                $lineNet  = round($qty * $price, 2);
                $lineTax  = round($lineNet * $taxRate / 100, 2);

                InvoiceItem::create([
                    'invoice_id'  => $invoice->id,
                    'description' => $item['description'],
                    'unit'        => $item['unit'],
                    'quantity'    => $qty,
                    'unit_price'  => $price,
                    'tax_rate'    => $taxRate,
                    'tax_amount'  => $lineTax,
                    'total'       => round($lineNet + $lineTax, 2),
                ]);
            }
        }

        return $invoice;
    }

    /**
     * Idempotent transaction upsert keyed on dedup_hash.
     */
    private function upsertTransaction(Tenant $tenant, array $data): BankTransaction
    {
        $subHeadSlug = $data['sub_head_slug'] ?? null;
        unset($data['sub_head_slug']);

        $data['dedup_hash'] = BankTransaction::makeDedupHash(
            $data['transaction_date'],
            $data['amount'],
            $data['type'],
            $data['bank_reference'] ?? $data['raw_narration']
        );

        $tx = BankTransaction::withoutGlobalScope('tenant')->updateOrCreate(
            ['tenant_id' => $tenant->id, 'dedup_hash' => $data['dedup_hash']],
            array_merge($data, ['tenant_id' => $tenant->id, 'is_duplicate' => false])
        );

        if ($subHeadSlug && $tx->wasRecentlyCreated) {
            $subHead = NarrationSubHead::withoutGlobalScope('tenant')
                ->whereHas('narrationHead', fn ($q) => $q->where('tenant_id', $tenant->id))
                ->where('slug', $subHeadSlug)
                ->first();

            if ($subHead) {
                $tx->updateQuietly([
                    'narration_head_id'     => $subHead->narration_head_id,
                    'narration_sub_head_id' => $subHead->id,
                ]);
            }
        }

        return $tx->fresh();
    }
}
