<?php

namespace Database\Seeders;

use App\Models\NarrationRule;
use App\Models\NarrationSubHead;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class NarrationRuleSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found — skipping NarrationRuleSeeder.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }

        $this->command->info('Narration rules seeded for all tenants.');
    }

    public function seedForTenant(Tenant $tenant): void
    {
        // Cache all sub-heads for this tenant to avoid N+1 queries
        $subHeadCache = NarrationSubHead::whereHas('narrationHead', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        })->get()->keyBy('slug');

        foreach ($this->defaultRuleDefinitions() as $rule) {
            $subHead = $subHeadCache->get($rule['sub']);

            if (!$subHead) {
                $this->command?->warn("  Missing sub-head [{$rule['sub']}] for tenant {$tenant->name}");
                continue;
            }

            NarrationRule::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id'        => $tenant->id,
                    'match_value'      => $rule['match'],
                    'match_type'       => $rule['match_type'] ?? 'contains',
                    'transaction_type' => $rule['type'],
                ],
                [
                    'narration_head_id'     => $subHead->narration_head_id,
                    'narration_sub_head_id' => $subHead->id,
                    'note_template'         => $rule['note_template'] ?? null,
                    'party_name'            => $rule['party_name'] ?? null,
                    'priority'              => $rule['priority'] ?? 10,
                    'is_active'             => true,
                    'source'                => 'manual',
                ]
            );
        }
    }

    private function defaultRuleDefinitions(): array
    {
        return [
            // ── Regex rules — extract party dynamically from narration ────────

            // ── Salary regex (priority 1 — very specific pattern, no keyword rule overlap) ──
            // "SAL-APR-2025-JOHN DOE" or "SAL-APR-2026-PREETHI NAIR"
            [
                'match'         => '/SAL(?:ARY)?[-\/][A-Z]{3}[-\/]\d{4}[-\/](?P<party>[A-Z][A-Z\s]+)/i',
                'match_type'    => 'regex',
                'type'          => 'debit',
                'sub'           => 'net_salary',
                'priority'      => 1,
                'note_template' => 'Salary – {party} ({date})',
            ],

            // ── Generic UPI/NEFT regex (priority 5 — fallback for UNKNOWN parties) ──
            // Keyword rules (priority 1-3) take precedence for known brands like Uber, Swiggy etc.
            // These only fire when no keyword rule matches, extracting the party from the narration.

            // NEFT credit with txn ID: "NEFT/CR/2847263/SUNTECH SOLUTIONS/HDFC"
            [
                'match'         => '/NEFT[\/\s]CR[\/\s]\d+[\/\s](?P<party>[A-Z][A-Z\s\.]+)[\/\s]/i',
                'match_type'    => 'regex',
                'type'          => 'credit',
                'sub'           => 'product_sales',
                'priority'      => 5,
                'note_template' => 'NEFT receipt from {party} – ₹{amount}',
            ],
            // NEFT credit dash-separated (no txn ID): "NEFT CR-ANANYA SINGH-REF-BANK"
            [
                'match'         => '/NEFT[\s\-]CR[\s\-](?P<party>[A-Z][A-Z\s]+)[\s\-]/i',
                'match_type'    => 'regex',
                'type'          => 'credit',
                'sub'           => 'product_sales',
                'priority'      => 6,
                'note_template' => 'NEFT receipt from {party} – ₹{amount}',
            ],
            // NEFT debit with txn ID: "NEFT/DR/2847263/RAJAN MEHTA/SBI"
            [
                'match'         => '/NEFT[\/\s]DR[\/\s]\d+[\/\s](?P<party>[A-Z][A-Z\s\.]+)[\/\s]/i',
                'match_type'    => 'regex',
                'type'          => 'debit',
                'sub'           => 'supplier_pay',
                'priority'      => 5,
                'note_template' => 'NEFT payment to {party} – ₹{amount}',
            ],
            // NEFT debit dash-separated (no txn ID): "NEFT DR-XYZ PROPERTIES-REF-BANK"
            [
                'match'         => '/NEFT[\s\-]DR[\s\-](?P<party>[A-Z][A-Z\s]+)[\s\-]/i',
                'match_type'    => 'regex',
                'type'          => 'debit',
                'sub'           => 'supplier_pay',
                'priority'      => 6,
                'note_template' => 'NEFT payment to {party} – ₹{amount}',
            ],
            // UPI credit with txn ID: "UPI/CR/123456/RAJAN MEHTA/upi@sbi"
            [
                'match'         => '/UPI[\/\s]CR[\/\s]\d+[\/\s](?P<party>[A-Z][A-Z\s\.]+)[\/\s]/i',
                'match_type'    => 'regex',
                'type'          => 'credit',
                'sub'           => 'product_sales',
                'priority'      => 5,
                'note_template' => 'UPI receipt from {party} – ₹{amount}',
            ],
            // UPI debit with txn ID: "UPI/DR/123456/ZOMATO INDIA/KKBK/..."
            [
                'match'         => '/UPI[\/\s]DR[\/\s]\d+[\/\s](?P<party>[A-Z][A-Z\s\.]+)[\/\s]/i',
                'match_type'    => 'regex',
                'type'          => 'debit',
                'sub'           => 'supplier_pay',
                'priority'      => 5,
                'note_template' => 'UPI payment to {party} – ₹{amount}',
            ],

            // ── Keyword rules — static party for known vendors ────────────────
            ['match' => 'uber',         'type' => 'debit',  'sub' => 'taxi_ride',      'priority' => 2,  'party_name' => 'Uber India',          'note_template' => '{party} trip – ₹{amount}'],
            ['match' => 'ola',          'type' => 'debit',  'sub' => 'taxi_ride',      'priority' => 2,  'party_name' => 'Ola Cabs',            'note_template' => '{party} trip – ₹{amount}'],
            ['match' => 'rapido',       'type' => 'debit',  'sub' => 'taxi_ride',      'priority' => 2,  'party_name' => 'Rapido',              'note_template' => '{party} – ₹{amount}'],
            ['match' => 'swiggy',       'type' => 'debit',  'sub' => 'food_catering',  'priority' => 2,  'party_name' => 'Swiggy',              'note_template' => 'Pantry: {party} order – ₹{amount}'],
            ['match' => 'zomato',       'type' => 'debit',  'sub' => 'food_catering',  'priority' => 2,  'party_name' => 'Zomato',              'note_template' => 'Pantry: {party} order – ₹{amount}'],
            ['match' => 'indigo',       'type' => 'debit',  'sub' => 'travel_booking', 'priority' => 2,  'party_name' => 'IndiGo Airlines',     'note_template' => '{party} flight booking – ₹{amount}'],
            ['match' => 'makemytrip',   'type' => 'debit',  'sub' => 'travel_booking', 'priority' => 2,  'party_name' => 'MakeMyTrip',          'note_template' => '{party} booking – ₹{amount}'],
            ['match' => 'irctc',        'type' => 'debit',  'sub' => 'travel_booking', 'priority' => 2,  'party_name' => 'IRCTC',               'note_template' => '{party} train ticket – ₹{amount}'],
            ['match' => 'amazon',       'type' => 'debit',  'sub' => 'stock_purchase', 'priority' => 3,  'party_name' => 'Amazon',              'note_template' => '{party} business purchase – ₹{amount}'],
            ['match' => 'flipkart',     'type' => 'debit',  'sub' => 'stock_purchase', 'priority' => 3,  'party_name' => 'Flipkart',            'note_template' => '{party} business purchase – ₹{amount}'],
            ['match' => 'petrol',       'type' => 'debit',  'sub' => 'fuel',           'priority' => 2,  'note_template' => 'Fuel – ₹{amount} on {date}'],
            ['match' => 'emi',          'type' => 'debit',  'sub' => 'bank_emi',       'priority' => 2,  'note_template' => 'Loan EMI – ₹{amount} on {date}'],
            ['match' => 'neft charges', 'type' => 'debit',  'sub' => 'bank_charges',   'priority' => 1,  'note_template' => 'Bank charges – ₹{amount}'],
            ['match' => 'bank charges', 'type' => 'debit',  'sub' => 'bank_charges',   'priority' => 1,  'note_template' => 'Bank charges – ₹{amount}'],
            ['match' => 'loan disbursed','type' => 'credit','sub' => 'bank_loan',       'priority' => 1,  'note_template' => 'Loan disbursement – ₹{amount}'],
            ['match' => 'refund',       'type' => 'credit', 'sub' => 'refund_cr',       'priority' => 1,  'note_template' => 'Refund/reversal – ₹{amount}'],
            ['match' => 'advance',      'type' => 'credit', 'sub' => 'project_advance', 'priority' => 3,  'note_template' => 'Customer advance – ₹{amount}'],
            ['match' => 'service fee',  'type' => 'credit', 'sub' => 'service_fees',    'priority' => 2,  'note_template' => 'Service fee inward – ₹{amount}'],
            ['match' => 'salary',       'type' => 'debit',  'sub' => 'net_salary',      'priority' => 5,  'note_template' => 'Salary payout – ₹{amount}'],
            ['match' => 'vendor',       'type' => 'debit',  'sub' => 'supplier_pay',    'priority' => 8,  'note_template' => 'Vendor payment – ₹{amount}'],
        ];
    }
}
