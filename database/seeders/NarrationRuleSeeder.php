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
                    'match_type'       => 'contains',
                    'transaction_type' => $rule['type'],
                ],
                [
                    'narration_head_id'     => $subHead->narration_head_id,
                    'narration_sub_head_id' => $subHead->id,
                    'note_template'         => $rule['note_template'] ?? null,
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
            // Credit rules
            ['match' => 'neft cr',         'type' => 'credit', 'sub' => 'product_sales',   'priority' => 2,  'note_template' => 'Sales Remittance'],
            ['match' => 'service fee',      'type' => 'credit', 'sub' => 'service_fees',    'priority' => 2,  'note_template' => 'Professional Fee Inward'],
            ['match' => 'loan disbursed',   'type' => 'credit', 'sub' => 'bank_loan',        'priority' => 1,  'note_template' => 'Loan Payout Receipt'],
            ['match' => 'advance',          'type' => 'credit', 'sub' => 'project_advance',  'priority' => 3,  'note_template' => 'Customer Project Advance'],
            ['match' => 'refund',           'type' => 'credit', 'sub' => 'refund_cr',        'priority' => 1,  'note_template' => 'Transaction Reversal/Refund'],
            // Debit rules
            ['match' => 'uber',             'type' => 'debit',  'sub' => 'taxi_ride',        'priority' => 1,  'note_template' => 'Uber Trip Expenses'],
            ['match' => 'ola',              'type' => 'debit',  'sub' => 'taxi_ride',        'priority' => 1,  'note_template' => 'Ola Trip Expenses'],
            ['match' => 'rapido',           'type' => 'debit',  'sub' => 'taxi_ride',        'priority' => 1,  'note_template' => 'Local Delivery/Rapido'],
            ['match' => 'petrol',           'type' => 'debit',  'sub' => 'fuel',             'priority' => 2,  'note_template' => 'General Fuel Spend'],
            ['match' => 'swiggy',           'type' => 'debit',  'sub' => 'food_catering',    'priority' => 1,  'note_template' => 'Pantry: Swiggy Order'],
            ['match' => 'zomato',           'type' => 'debit',  'sub' => 'food_catering',    'priority' => 1,  'note_template' => 'Pantry: Zomato Order'],
            ['match' => 'sal-apr',          'type' => 'debit',  'sub' => 'net_salary',       'priority' => 1,  'note_template' => 'Monthly Staff Salary Payout'],
            ['match' => 'salary',           'type' => 'debit',  'sub' => 'net_salary',       'priority' => 2,  'note_template' => 'Monthly Staff Salary Payout'],
            ['match' => 'vendor',           'type' => 'debit',  'sub' => 'supplier_pay',     'priority' => 5,  'note_template' => 'General Vendor Payout'],
            ['match' => 'indigo',           'type' => 'debit',  'sub' => 'travel_booking',   'priority' => 1,  'note_template' => 'Indigo Flight Booking'],
            ['match' => 'makemytrip',       'type' => 'debit',  'sub' => 'travel_booking',   'priority' => 1,  'note_template' => 'Travel Agent Booking (MMT)'],
            ['match' => 'irctc',            'type' => 'debit',  'sub' => 'travel_booking',   'priority' => 1,  'note_template' => 'Train Ticket Booking'],
            ['match' => 'amazon',           'type' => 'debit',  'sub' => 'stock_purchase',   'priority' => 3,  'note_template' => 'Amazon Business Purchase'],
            ['match' => 'flipkart',         'type' => 'debit',  'sub' => 'stock_purchase',   'priority' => 3,  'note_template' => 'Flipkart Business Purchase'],
            ['match' => 'emi',              'type' => 'debit',  'sub' => 'bank_emi',         'priority' => 1,  'note_template' => 'Monthly Loan Installment'],
            ['match' => 'neft charges',     'type' => 'debit',  'sub' => 'bank_charges',     'priority' => 1,  'note_template' => 'Bank Service Fees'],
            ['match' => 'bank charges',     'type' => 'debit',  'sub' => 'bank_charges',     'priority' => 1,  'note_template' => 'Bank Service Fees'],
        ];
    }
}
