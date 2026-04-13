<?php

namespace Database\Seeders;

use App\Models\BankTransaction;
use App\Models\NarrationRule;
use App\Models\NarrationSubHead;
use App\Models\Tenant;
use App\Services\Banking\NarrationRuleEngine;
use Illuminate\Database\Seeder;

class BankTransactionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Step 1: Ensure rules exist ─────────────────────────────────────
        $this->call(NarrationRuleSeeder::class);

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found — skipping BankTransactionSeeder.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->command->info("\n── Seeding transactions for tenant: {$tenant->name} ──");
            $this->seedForTenant($tenant);
        }

        $this->command->info("\nBank transactions seeded for all tenants.");
    }

    private function seedForTenant(Tenant $tenant): void
    {
        $engine = app(NarrationRuleEngine::class);

        $sub = fn (string $slug) => NarrationSubHead::whereHas(
            'narrationHead', fn ($q) => $q->where('tenant_id', $tenant->id)
        )->where('slug', $slug)->first();

        // ── Step 2: Seed a learned rule to demonstrate party-name-first matching ──
        // Simulates what happens after an accountant corrects a transaction and
        // ticks "Save as rule". The note template uses {party}/{amount}/{date}
        // placeholders so it stays accurate for every future transaction.
        $rentSubHead = $sub('supplier_pay');
        if ($rentSubHead) {
            NarrationRule::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id'        => $tenant->id,
                    'match_value'      => 'xyz properties',   // normalised party name (no "pvt ltd")
                    'match_type'       => 'contains',
                    'transaction_type' => 'debit',
                ],
                [
                    'narration_head_id'     => $rentSubHead->narration_head_id,
                    'narration_sub_head_id' => $rentSubHead->id,
                    'note_template'         => 'Office rent – {party} – ₹{amount} ({date})',
                    'party_name'            => 'Xyz Properties Pvt Ltd',
                    'priority'              => 3,
                    'is_active'             => true,
                    'source'                => 'learned',
                ]
            );
        }

        // ── Raw transactions — rule engine will be tried first ─────────────
        // Each entry has only what a real bank SMS/statement would contain.
        // 'expected_source' and 'expected_party' are assertions for the output log only.
        $rawTransactions = [
            [
                'transaction_date'  => '2026-04-01',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26040100123/SUNTECH',
                'raw_narration'     => 'NEFT/CR/26040100123/SUNTECH SOLUTIONS PVT LTD/KOTAK BANK',
                'type'              => 'credit',
                'amount'            => 59000.00,
                'balance_after'     => 259000.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Suntech Solutions Pvt Ltd',  // regex capture
            ],
            [
                'transaction_date'  => '2026-04-02',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040212345/ZOMATO',
                'raw_narration'     => 'UPI/DR/26040212345/ZOMATO INDIA/KKBK/zomato@kotak/Team lunch',
                'type'              => 'debit',
                'amount'            => 2340.00,
                'balance_after'     => 256660.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Zomato',                     // keyword rule (priority 2) beats UPI regex (priority 5)
            ],
            [
                'transaction_date'  => '2026-04-03',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040398765/UBER',
                'raw_narration'     => 'UPI/DR/26040398765/Uber India Systems/RATN/uber@hdfcbank/Cab ride',
                'type'              => 'debit',
                'amount'            => 450.00,
                'balance_after'     => 256210.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Uber India',                 // keyword rule (priority 2) beats UPI regex (priority 5)
            ],
            [
                'transaction_date'  => '2026-04-05',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26040500456/RAJAN',
                'raw_narration'     => 'NEFT/CR/26040500456/RAJAN ENTERPRISES/ICICI BANK',
                'type'              => 'credit',
                'amount'            => 118000.00,
                'balance_after'     => 374210.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Rajan Enterprises',          // regex capture
            ],
            [
                'transaction_date'  => '2026-04-05',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'ACH/26040501/HDFCBNK',
                'raw_narration'     => 'ACH DR-HDFC BANK EMI-LN00234567-HDFC BANK',
                'type'              => 'debit',
                'amount'            => 18500.00,
                'balance_after'     => 355710.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => null,                         // keyword rule, no party_name on emi rule
            ],
            [
                'transaction_date'  => '2026-04-08',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040822222/SWIGGY',
                'raw_narration'     => 'UPI/DR/26040822222/BUNDL TECHNOLOGIES/KKBK/swiggy@kotak/Office food order',
                'type'              => 'debit',
                'amount'            => 1860.00,
                'balance_after'     => 394100.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Swiggy',                     // keyword rule (priority 2) wins over "Bundl Technologies" from UPI regex
            ],
            [
                'transaction_date'  => '2026-04-09',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'IMPS/26040933333/SALARY',
                'raw_narration'     => 'SAL-APR-2026-RAJESH KUMAR SHARMA',
                'type'              => 'debit',
                'amount'            => 42000.00,
                'balance_after'     => 352100.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Rajesh Kumar Sharma',        // regex salary capture
            ],
            [
                'transaction_date'  => '2026-04-09',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'IMPS/26040944444/SALARY',
                'raw_narration'     => 'SAL-APR-2026-PREETHI NAIR',
                'type'              => 'debit',
                'amount'            => 38500.00,
                'balance_after'     => 313600.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Preethi Nair',               // regex salary capture
            ],
            [
                'transaction_date'  => '2026-04-10',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26041055555/IRCTC',
                'raw_narration'     => 'UPI/DR/26041055555/IRCTC LTD/PYTM/irctc@paytm/Train tickets Bangalore-Mumbai',
                'type'              => 'debit',
                'amount'            => 3200.00,
                'balance_after'     => 310400.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'IRCTC',                      // keyword rule (priority 2) wins over "Irctc Ltd" from UPI regex
            ],
            [
                'transaction_date'  => '2026-04-10',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'CHG/26041066666/HDFCBNK',
                'raw_narration'     => 'NEFT CHARGES-OTH-APR2026-HDFC BANK',
                'type'              => 'debit',
                'amount'            => 118.00,
                'balance_after'     => 310282.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => null,
            ],
            [
                'transaction_date'  => '2026-04-10',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26041077777/FLIPKART',
                'raw_narration'     => 'UPI/DR/26041077777/FLIPKART INTERNET PVT LTD/RATN/flipkart@indus/Laptop stand',
                'type'              => 'debit',
                'amount'            => 5499.00,
                'balance_after'     => 304783.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Flipkart',                   // keyword rule (priority 3) wins over "Flipkart Internet Pvt Ltd" from UPI regex
            ],

            // ── These should NOT match any rule (no rule covers them) ──────
            [
                'transaction_date'  => '2026-04-07',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040754321/AMAZON',
                'raw_narration'     => 'UPI/DR/26040754321/AMAZON SELLER SERVICES/RATN/amazon@apl/Office supplies',
                'type'              => 'debit',
                'amount'            => 4750.00,
                'balance_after'     => 350960.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Amazon',                     // keyword rule (priority 3) wins over "Amazon Seller Services" from UPI regex
                // Fallback used when no rule matches
                'fallback_sub_head'  => 'stock_purchase',
                'fallback_note'      => 'Amazon Business Purchase',
                'fallback_party'     => 'Amazon',
                'fallback_confidence'=> 0.88,
                'fallback_suggestions' => [
                    ['head' => 'Stock & Purchases', 'sub_head' => 'Stock Purchase', 'confidence' => 0.88, 'reasoning' => 'Amazon Seller Services UPI payment likely an inventory or office supply purchase.'],
                    ['head' => 'Office Expenses',   'sub_head' => 'Office Supplies',  'confidence' => 0.09, 'reasoning' => 'Could be office consumables if not for resale.'],
                ],
            ],
            [
                'transaction_date'  => '2026-04-08',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26040811111/ANANYA',
                'raw_narration'     => 'NEFT CR-ANANYA SINGH-ADV-PROJ003-AXIS BANK',
                'type'              => 'credit',
                'amount'            => 45000.00,
                'balance_after'     => 395960.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Ananya Singh',               // NEFT dash regex extracts party
                'fallback_sub_head'  => 'project_advance',
                'fallback_note'      => 'Customer Project Advance',
                'fallback_party'     => 'Ananya Singh',
                'fallback_confidence'=> 0.82,
                'fallback_suggestions' => [
                    ['head' => 'Revenue', 'sub_head' => 'Project Advance', 'confidence' => 0.82, 'reasoning' => '"ADV-PROJ003" strongly indicates a project advance payment from a client.'],
                    ['head' => 'Revenue', 'sub_head' => 'Service Fees',    'confidence' => 0.14, 'reasoning' => 'Could be a service fee payment if PROJ003 is a service engagement.'],
                ],
            ],
            [
                'transaction_date'  => '2026-04-11',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26041188888/PRIYA',
                'raw_narration'     => 'NEFT CR-PRIYA MEHTA-INV2026003-HDFC BANK',
                'type'              => 'credit',
                'amount'            => 29500.00,
                'balance_after'     => 334283.00,
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Priya Mehta',                // NEFT dash regex extracts party
                'fallback_sub_head'  => 'service_fees',
                'fallback_note'      => 'Sales Remittance',
                'fallback_party'     => 'Priya Mehta',
                'fallback_confidence'=> 0.79,
                'fallback_suggestions' => [
                    ['head' => 'Revenue', 'sub_head' => 'Service Fees',    'confidence' => 0.79, 'reasoning' => 'NEFT credit referencing an invoice number from an individual — professional service fees.'],
                    ['head' => 'Revenue', 'sub_head' => 'Product Sales',   'confidence' => 0.18, 'reasoning' => 'Could be product sale payment if client trades goods.'],
                ],
            ],

            // ── Learned rule — matched via party name (Tier A) ───────────────
            // Simulates an SMS where SmsParserAgent already extracted the party.
            // The raw narration alone ("Dear Customer, NEFT DR...") would NOT match
            // any keyword/regex rule — but the AI-parsed partyName "Xyz Properties Pvt Ltd"
            // contains "xyz properties", which hits the learned rule seeded above.
            // Demonstrates party-name-first matching and note template with placeholders.
            [
                'transaction_date'  => '2026-04-12',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26041299999/MISC',
                'raw_narration'     => 'Dear Customer, NEFT DR INR 75,000 to XYZ PROPERTIES PVT LTD on 12-Apr-26 Ref 26041299999',
                'type'              => 'debit',
                'amount'            => 75000.00,
                'balance_after'     => 259283.00,
                // Simulated AI-parsed party name (what SmsParserAgent would return)
                'party_name_hint'   => 'Xyz Properties Pvt Ltd',
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Xyz Properties Pvt Ltd',     // matched via partyName (Tier A), not raw narration
                'expected_note'     => 'Office rent – Xyz Properties Pvt Ltd – ₹75,000.00 (12-Apr-2026)',
            ],

            // ── Learned rule — same party, second month (note template fills new values) ──
            // Proves {amount} and {date} placeholders update correctly each time.
            [
                'transaction_date'  => '2026-05-01',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26050100001/MISC',
                'raw_narration'     => 'Dear Customer, NEFT DR INR 75,000 to XYZ PROPERTIES PVT LTD on 01-May-26 Ref 26050100001',
                'type'              => 'debit',
                'amount'            => 75000.00,
                'balance_after'     => 184283.00,
                'party_name_hint'   => 'Xyz Properties Pvt Ltd',
                'expected_source'   => 'rule_based',
                'expected_party'    => 'Xyz Properties Pvt Ltd',
                'expected_note'     => 'Office rent – Xyz Properties Pvt Ltd – ₹75,000.00 (01-May-2026)',
            ],
        ];

        $pass = 0;
        $fail = 0;

        foreach ($rawTransactions as $data) {
            $expectedSource = $data['expected_source'];
            $expectedParty  = $data['expected_party'];
            $expectedNote   = $data['expected_note']   ?? null;
            $partyNameHint  = $data['party_name_hint'] ?? null;  // simulates AI-parsed party from SMS
            $fallback       = array_filter([
                'sub_head'    => $data['fallback_sub_head']    ?? null,
                'note'        => $data['fallback_note']        ?? null,
                'party'       => $data['fallback_party']       ?? null,
                'confidence'  => $data['fallback_confidence']  ?? null,
                'suggestions' => $data['fallback_suggestions'] ?? null,
            ]);

            // Strip seeder-only keys before persisting
            unset($data['expected_source'], $data['expected_party'], $data['expected_note'],
                  $data['party_name_hint'],
                  $data['fallback_sub_head'], $data['fallback_note'],
                  $data['fallback_party'], $data['fallback_confidence'],
                  $data['fallback_suggestions']);

            // ── Run the rule engine ────────────────────────────────────────
            // Pass partyNameHint when present — simulates what the SMS/email AI parser
            // would have extracted before handing off to the pipeline.
            $suggestion = $engine->match(
                narration:  $data['raw_narration'],
                type:       $data['type'],
                amount:     $data['amount'],
                partyName:  $partyNameHint,
                tenantId:   $tenant->id,
            );

            // ── Log result ────────────────────────────────────────────────
            $matched = $suggestion !== null;
            $actualSource = $suggestion?->source ?? 'no_match';
            $actualParty  = $suggestion?->partyName;
            $actualNote   = $suggestion?->narrationNote;

            $ruleOk   = ($expectedSource === 'rule_based') === $matched;
            $partyOk  = $expectedParty === null || $actualParty === $expectedParty;
            $noteOk   = $expectedNote  === null || $actualNote  === $expectedNote;
            $allOk    = $ruleOk && $partyOk && $noteOk;

            $icon = $allOk ? '✓' : '✗';
            $this->command->line(sprintf(
                "  %s  %-55s | source: %-12s | party: %s",
                $icon,
                substr($data['raw_narration'], 0, 55),
                $actualSource,
                $actualParty ?? '(none)',
            ));

            // Print note for learned-rule transactions so the template output is visible
            if ($partyNameHint && $actualNote) {
                $this->command->line(sprintf(
                    "       note: %s",
                    $actualNote,
                ));
            }

            if (!$allOk) {
                $fail++;
                if (!$ruleOk) {
                    $this->command->warn("      expected source={$expectedSource}, got={$actualSource}");
                }
                if (!$partyOk) {
                    $this->command->warn("      expected party=\"{$expectedParty}\", got=\"{$actualParty}\"");
                }
                if (!$noteOk) {
                    $this->command->warn("      expected note=\"{$expectedNote}\"");
                    $this->command->warn("      got      note=\"{$actualNote}\"");
                }
            } else {
                $pass++;
            }

            // ── Resolve narration head/sub-head ───────────────────────────
            $headId    = $suggestion?->narrationHeadId;
            $subHeadId = $suggestion?->narrationSubHeadId;
            $note      = $suggestion?->narrationNote;
            $party     = $suggestion?->partyName;
            $source    = $suggestion?->source ?? 'manual';
            $confidence= $suggestion !== null ? 1.00 : null;
            $suggestions = [];

            if (!$suggestion && !empty($fallback['sub_head'])) {
                $subHead   = $sub($fallback['sub_head']);
                $headId    = $subHead?->narration_head_id;
                $subHeadId = $subHead?->id;
                $note      = $fallback['note'] ?? null;
                $party     = $fallback['party'] ?? null;
                $source    = $expectedSource ?? 'manual';
                $confidence= $fallback['confidence'] ?? null;
                $suggestions = $fallback['suggestions'] ?? [];
            }

            // ── Persist ───────────────────────────────────────────────────
            $hash = BankTransaction::makeDedupHash(
                $data['transaction_date'],
                $data['amount'],
                $data['type'],
                $data['bank_reference'] ?? $data['raw_narration'],
            );

            BankTransaction::withoutGlobalScope('tenant')->updateOrCreate(
                ['tenant_id' => $tenant->id, 'dedup_hash' => $hash],
                array_merge($data, [
                    'tenant_id'             => $tenant->id,
                    'narration_head_id'     => $headId,
                    'narration_sub_head_id' => $subHeadId,
                    'narration_note'        => $note,
                    'party_name'            => $party ?? $data['party_name'] ?? null,
                    'narration_source'      => $source,
                    'ai_confidence'         => $confidence,
                    'ai_suggestions'        => $suggestions ?: null,
                    'applied_rule_id'       => $suggestion?->appliedRuleId,
                    'review_status'         => 'pending',
                    'is_duplicate'          => false,
                    'dedup_hash'            => $hash,
                ])
            );
        }

        $total = $pass + $fail;
        $this->command->info("  Rule engine: {$pass}/{$total} passed" . ($fail > 0 ? " ({$fail} mismatches)" : ' ✓'));
    }
}
