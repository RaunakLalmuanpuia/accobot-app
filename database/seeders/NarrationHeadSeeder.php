<?php

namespace Database\Seeders;

use App\Models\NarrationHead;
use App\Models\NarrationSubHead;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class NarrationHeadSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found — skipping NarrationHeadSeeder.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->seedForTenant($tenant->id);
        }
    }

    public function seedForTenant(string $tenantId): void
    {
        foreach ($this->heads() as $i => $headData) {
            $subHeadsData = $headData['sub_heads'];
            unset($headData['sub_heads']);

            $head = NarrationHead::updateOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $headData['slug']],
                array_merge($headData, ['tenant_id' => $tenantId, 'sort_order' => $i, 'is_active' => true])
            );

            foreach ($subHeadsData as $j => $sub) {
                NarrationSubHead::updateOrCreate(
                    ['narration_head_id' => $head->id, 'slug' => $sub['slug']],
                    array_merge($sub, [
                        'narration_head_id' => $head->id,
                        'sort_order'        => $j,
                        'is_active'         => true,
                        'requires_party'    => $sub['requires_party'] ?? false,
                    ])
                );
            }
        }
    }

    private function heads(): array
    {
        return [
            ['name' => 'Revenue', 'slug' => 'revenue', 'type' => 'credit', 'sub_heads' => [
                ['name' => 'Product Sales',  'slug' => 'product_sales', 'ledger_code' => '4001'],
                ['name' => 'Service Fees',   'slug' => 'service_fees',  'ledger_code' => '4002'],
            ]],
            ['name' => 'Loan', 'slug' => 'loan_credit', 'type' => 'credit', 'sub_heads' => [
                ['name' => 'Bank Loan Disbursal', 'slug' => 'bank_loan',      'ledger_code' => '2101'],
                ['name' => 'Unsecured Loan',      'slug' => 'unsecured_loan', 'ledger_code' => '2102', 'requires_party' => true],
            ]],
            ['name' => 'Advance Payment by Client', 'slug' => 'advance_payment', 'type' => 'credit', 'sub_heads' => [
                ['name' => 'Project Advance', 'slug' => 'project_advance', 'ledger_code' => '2201', 'requires_party' => true],
            ]],
            ['name' => 'Transaction Reversal', 'slug' => 'reversal_credit', 'type' => 'credit', 'sub_heads' => [
                ['name' => 'Refund Received', 'slug' => 'refund_cr', 'ledger_code' => '4902'],
            ]],
            ['name' => 'Suspense', 'slug' => 'suspense_credit', 'type' => 'credit', 'sub_heads' => [
                ['name' => 'Unidentified Credit', 'slug' => 'unidentified_cr', 'ledger_code' => '9001'],
            ]],
            ['name' => 'Expense', 'slug' => 'general_expense', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'General Office Expense', 'slug' => 'office_exp', 'ledger_code' => '6001'],
            ]],
            ['name' => 'Salary', 'slug' => 'salary_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Staff Salary', 'slug' => 'net_salary', 'ledger_code' => '6101', 'requires_party' => true],
            ]],
            ['name' => 'Vendor Payments', 'slug' => 'vendor_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Supplier Payment', 'slug' => 'supplier_pay', 'ledger_code' => '6201', 'requires_party' => true],
            ]],
            ['name' => 'F&B', 'slug' => 'fnb_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Food & Catering', 'slug' => 'food_catering', 'ledger_code' => '6301'],
                ['name' => 'Pantry Expenses', 'slug' => 'pantry_exp',    'ledger_code' => '6302'],
            ]],
            ['name' => 'Conveyance', 'slug' => 'conveyance_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Uber / Taxi',  'slug' => 'taxi_ride', 'ledger_code' => '6601'],
                ['name' => 'Fuel/Petrol',  'slug' => 'fuel',      'ledger_code' => '6602'],
            ]],
            ['name' => 'Tickets and Hotels', 'slug' => 'travel_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Travel Booking', 'slug' => 'travel_booking', 'ledger_code' => '6501'],
            ]],
            ['name' => 'Inventory', 'slug' => 'inventory_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Direct Material Purchase', 'slug' => 'stock_purchase', 'ledger_code' => '5001'],
            ]],
            ['name' => 'Loan Payment/EMI', 'slug' => 'emi_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Bank EMI', 'slug' => 'bank_emi', 'ledger_code' => '2103'],
            ]],
            ['name' => 'Loans & Advances', 'slug' => 'loans_advances_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Employee Advance',  'slug' => 'emp_advance',  'ledger_code' => '1301', 'requires_party' => true],
                ['name' => 'Security Deposits', 'slug' => 'sec_deposit',  'ledger_code' => '1302'],
            ]],
            ['name' => 'Legal/CA', 'slug' => 'legal_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Professional Fees', 'slug' => 'prof_fees', 'ledger_code' => '6801'],
            ]],
            ['name' => 'Hardware & Tools', 'slug' => 'hardware_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Consumable Tools', 'slug' => 'small_tools', 'ledger_code' => '6402'],
            ]],
            ['name' => 'Reimbursement', 'slug' => 'reimbursement_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Employee Reimbursement', 'slug' => 'emp_reimburse', 'ledger_code' => '6104', 'requires_party' => true],
            ]],
            ['name' => 'Capitalized Investment', 'slug' => 'capitalized_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Fixed Asset Purchase', 'slug' => 'fixed_asset', 'ledger_code' => '1001'],
                ['name' => 'Machinery',            'slug' => 'machinery',   'ledger_code' => '1002'],
            ]],
            ['name' => 'Miscellaneous', 'slug' => 'misc_debit', 'type' => 'debit', 'sub_heads' => [
                ['name' => 'Bank Charges', 'slug' => 'bank_charges', 'ledger_code' => '6902'],
                ['name' => 'Other Misc',   'slug' => 'misc_other',   'ledger_code' => '6901'],
            ]],
        ];
    }
}
