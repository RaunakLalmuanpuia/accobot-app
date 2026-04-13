<?php

namespace Database\Seeders;

use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\NarrationSubHead;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class BankTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found — skipping BankTransactionSeeder.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant);
        }

        $this->command->info('Bank transactions seeded for all tenants.');
    }

    private function seedForTenant(Tenant $tenant): void
    {
        $sub = fn (string $slug) => NarrationSubHead::whereHas(
            'narrationHead', fn ($q) => $q->where('tenant_id', $tenant->id)
        )->where('slug', $slug)->first();

        $transactions = [

            // ── Reviewed (narration complete) ─────────────────────────────────

            [
                'transaction_date'  => '2026-04-01',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26040100123/SUNTECH',
                'raw_narration'     => 'NEFT CR-SUNTECH SOLUTIONS PVT LTD-INV2026001-KOTAK BANK',
                'type'              => 'credit',
                'amount'            => 59000.00,
                'balance_after'     => 259000.00,
                'party_name'        => 'Suntech Solutions Pvt Ltd',
                'narration_note'    => 'Payment received for INV2026001',
                'sub_head_slug'     => 'service_fees',
                'narration_source'  => 'manual',
                'review_status'     => 'reviewed',
                'is_duplicate'      => false,
                'ai_confidence'     => null,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-02',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040212345/ZOMATO',
                'raw_narration'     => 'UPI/DR/26040212345/ZOMATO INDIA/KKBK/zomato@kotak/Team lunch',
                'type'              => 'debit',
                'amount'            => 2340.00,
                'balance_after'     => 256660.00,
                'party_name'        => 'Zomato India',
                'narration_note'    => 'Team lunch ordered via Zomato',
                'sub_head_slug'     => 'food_catering',
                'narration_source'  => 'rule_based',
                'review_status'     => 'reviewed',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-03',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040398765/UBER',
                'raw_narration'     => 'UPI/DR/26040398765/Uber India Systems/RATN/uber@hdfcbank/Cab ride',
                'type'              => 'debit',
                'amount'            => 450.00,
                'balance_after'     => 256210.00,
                'party_name'        => 'Uber India',
                'narration_note'    => 'Cab fare - client visit',
                'sub_head_slug'     => 'taxi_ride',
                'narration_source'  => 'rule_based',
                'review_status'     => 'reviewed',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-05',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26040500456/RAJAN',
                'raw_narration'     => 'NEFT CR-RAJAN ENTERPRISES-INV2026002-ICICI BANK',
                'type'              => 'credit',
                'amount'            => 118000.00,
                'balance_after'     => 374210.00,
                'party_name'        => 'Rajan Enterprises',
                'narration_note'    => 'Payment received for INV2026002',
                'sub_head_slug'     => 'product_sales',
                'narration_source'  => 'manual',
                'review_status'     => 'reviewed',
                'is_duplicate'      => false,
                'ai_confidence'     => null,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-05',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'ACH/26040501/HDFCBNK',
                'raw_narration'     => 'ACH DR-HDFC BANK EMI-LN00234567-HDFC BANK',
                'type'              => 'debit',
                'amount'            => 18500.00,
                'balance_after'     => 355710.00,
                'party_name'        => 'HDFC Bank',
                'narration_note'    => 'Monthly EMI for business loan LN00234567',
                'sub_head_slug'     => 'bank_emi',
                'narration_source'  => 'rule_based',
                'review_status'     => 'reviewed',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],

            // ── Pending — rule matched, awaiting human approval ───────────────

            [
                'transaction_date'  => '2026-04-08',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040822222/SWIGGY',
                'raw_narration'     => 'UPI/DR/26040822222/BUNDL TECHNOLOGIES/KKBK/swiggy@kotak/Office food order',
                'type'              => 'debit',
                'amount'            => 1860.00,
                'balance_after'     => 394100.00,
                'party_name'        => 'Swiggy',
                'narration_note'    => 'Pantry: Swiggy Order',
                'sub_head_slug'     => 'food_catering',
                'narration_source'  => 'rule_based',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-09',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'IMPS/26040933333/SALARY',
                'raw_narration'     => 'IMPS/DR/26040933333/RAJESH KUMAR SHARMA/SBIN/SAL-APR2026',
                'type'              => 'debit',
                'amount'            => 42000.00,
                'balance_after'     => 352100.00,
                'party_name'        => 'Rajesh Kumar Sharma',
                'narration_note'    => 'Monthly Staff Salary Payout',
                'sub_head_slug'     => 'net_salary',
                'narration_source'  => 'rule_based',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-09',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'IMPS/26040944444/SALARY',
                'raw_narration'     => 'IMPS/DR/26040944444/PREETHI NAIR/HDFC/SAL-APR2026',
                'type'              => 'debit',
                'amount'            => 38500.00,
                'balance_after'     => 313600.00,
                'party_name'        => 'Preethi Nair',
                'narration_note'    => 'Monthly Staff Salary Payout',
                'sub_head_slug'     => 'net_salary',
                'narration_source'  => 'rule_based',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-10',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26041055555/IRCTC',
                'raw_narration'     => 'UPI/DR/26041055555/IRCTC LTD/PYTM/irctc@paytm/Train tickets Bangalore-Mumbai',
                'type'              => 'debit',
                'amount'            => 3200.00,
                'balance_after'     => 310400.00,
                'party_name'        => 'IRCTC',
                'narration_note'    => 'Train Ticket Booking',
                'sub_head_slug'     => 'travel_booking',
                'narration_source'  => 'rule_based',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-10',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'CHG/26041066666/HDFCBNK',
                'raw_narration'     => 'NEFT CHARGES-OTH-APR2026-HDFC BANK',
                'type'              => 'debit',
                'amount'            => 118.00,
                'balance_after'     => 310282.00,
                'party_name'        => 'HDFC Bank',
                'narration_note'    => 'Bank Service Fees',
                'sub_head_slug'     => 'bank_charges',
                'narration_source'  => 'rule_based',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],
            [
                'transaction_date'  => '2026-04-10',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26041077777/FLIPKART',
                'raw_narration'     => 'UPI/DR/26041077777/FLIPKART INTERNET PVT LTD/RATN/flipkart@indus/Laptop stand and keyboard',
                'type'              => 'debit',
                'amount'            => 5499.00,
                'balance_after'     => 304783.00,
                'party_name'        => 'Flipkart',
                'narration_note'    => 'Flipkart Business Purchase',
                'sub_head_slug'     => 'stock_purchase',
                'narration_source'  => 'rule_based',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 1.00,
                'ai_suggestions'    => null,
            ],

            // ── Pending — AI suggested, no rule match ─────────────────────────

            [
                'transaction_date'  => '2026-04-07',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'UPI/26040754321/AMAZON',
                'raw_narration'     => 'UPI/DR/26040754321/AMAZON SELLER SERVICES/RATN/amazon@apl/Office supplies',
                'type'              => 'debit',
                'amount'            => 4750.00,
                'balance_after'     => 350960.00,
                'party_name'        => 'Amazon',
                'narration_note'    => 'Amazon Business Purchase',
                'sub_head_slug'     => 'stock_purchase',
                'narration_source'  => 'ai_suggested',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 0.88,
                'ai_suggestions'    => [
                    ['head' => 'Stock & Purchases', 'sub_head' => 'Stock Purchase', 'confidence' => 0.88, 'reasoning' => 'Amazon Seller Services UPI payment likely an inventory or office supply purchase.'],
                    ['head' => 'Office Expenses', 'sub_head' => 'Office Supplies', 'confidence' => 0.09, 'reasoning' => 'Could be office consumables if not for resale.'],
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
                'party_name'        => 'Ananya Singh',
                'narration_note'    => 'Customer Project Advance',
                'sub_head_slug'     => 'project_advance',
                'narration_source'  => 'ai_suggested',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 0.82,
                'ai_suggestions'    => [
                    ['head' => 'Revenue', 'sub_head' => 'Project Advance', 'confidence' => 0.82, 'reasoning' => '"ADV-PROJ003" strongly indicates a project advance payment from a client.'],
                    ['head' => 'Revenue', 'sub_head' => 'Service Fees', 'confidence' => 0.14, 'reasoning' => 'Could be a service fee payment if PROJ003 is a service engagement.'],
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
                'party_name'        => 'Priya Mehta',
                'narration_note'    => 'Sales Remittance',
                'sub_head_slug'     => 'service_fees',
                'narration_source'  => 'ai_suggested',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => 0.79,
                'ai_suggestions'    => [
                    ['head' => 'Revenue', 'sub_head' => 'Service Fees', 'confidence' => 0.79, 'reasoning' => 'NEFT credit referencing an invoice number from an individual likely denotes professional service fees.'],
                    ['head' => 'Revenue', 'sub_head' => 'Product Sales', 'confidence' => 0.18, 'reasoning' => 'Could be product sale payment if client trades goods.'],
                ],
            ],

            // ── Pending — no suggestion yet (rule engine + AI both inconclusive) ──

            [
                'transaction_date'  => '2026-04-12',
                'bank_account_name' => 'HDFC Current Account',
                'bank_reference'    => 'NEFT/26041299999/MISC',
                'raw_narration'     => 'NEFT DR-XYZ PROPERTIES PVT LTD-Q1RENT-SBI BANK',
                'type'              => 'debit',
                'amount'            => 75000.00,
                'balance_after'     => 259283.00,
                'party_name'        => null,
                'narration_note'    => null,
                'sub_head_slug'     => null,
                'narration_source'  => 'manual',
                'review_status'     => 'pending',
                'is_duplicate'      => false,
                'ai_confidence'     => null,
                'ai_suggestions'    => null,
            ],
        ];

        foreach ($transactions as $data) {
            $subHeadSlug = $data['sub_head_slug'] ?? null;
            unset($data['sub_head_slug']);

            // Compute dedup_hash to match the unique DB constraint
            $data['dedup_hash'] = BankTransaction::makeDedupHash(
                $data['transaction_date'],
                $data['amount'],
                $data['type'],
                $data['bank_reference'] ?? $data['raw_narration']
            );

            $txn = BankTransaction::withoutGlobalScope('tenant')->updateOrCreate(
                [
                    'tenant_id'   => $tenant->id,
                    'dedup_hash'  => $data['dedup_hash'],
                ],
                array_merge($data, ['tenant_id' => $tenant->id])
            );

            if ($subHeadSlug) {
                $subHead = $sub($subHeadSlug);
                if ($subHead) {
                    $txn->updateQuietly([
                        'narration_head_id'     => $subHead->narration_head_id,
                        'narration_sub_head_id' => $subHead->id,
                    ]);
                }
            }
        }
    }
}
