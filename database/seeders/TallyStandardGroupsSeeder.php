<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\TallyLedgerGroup;
use Illuminate\Database\Seeder;

class TallyStandardGroupsSeeder extends Seeder
{
    /**
     * The 28 standard Tally Prime ledger groups matching the Chart of Accounts.
     * tally_id is null so these don't conflict with real inbound sync records
     * (which are keyed on ['tenant_id', 'tally_id']). Hierarchy is preserved
     * via under_name; under_id is null since there is no real tally_id to reference.
     *
     * Format: [name, under_name]
     */
    private const GROUPS = [
        // Primary groups (no parent)
        ['Branch / Divisions',      null],
        ['Capital Account',         null],
        ['Current Assets',          null],
        ['Current Liabilities',     null],
        ['Direct Expenses',         null],
        ['Direct Incomes',          null],
        ['Fixed Assets',            null],
        ['Indirect Expenses',       null],
        ['Indirect Incomes',        null],
        ['Investments',             null],
        ['Loans (Liability)',        null],
        ['Misc. Expenses (ASSET)',  null],
        ['Purchase Accounts',       null],
        ['Sales Accounts',          null],
        ['Suspense A/c',            null],
        // Sub-groups — Capital Account
        ['Reserves & Surplus',      'Capital Account'],
        // Sub-groups — Current Assets
        ['Bank Accounts',           'Current Assets'],
        ['Cash-in-Hand',            'Current Assets'],
        ['Deposits (Asset)',         'Current Assets'],
        ['Loans & Advances (Asset)','Current Assets'],
        ['Stock-in-Hand',           'Current Assets'],
        ['Sundry Debtors',          'Current Assets'],
        // Sub-groups — Current Liabilities
        ['Duties & Taxes',          'Current Liabilities'],
        ['Provisions',              'Current Liabilities'],
        ['Sundry Creditors',        'Current Liabilities'],
        // Sub-groups — Loans (Liability)
        ['Bank OD A/c',             'Loans (Liability)'],
        ['Secured Loans',           'Loans (Liability)'],
        ['Unsecured Loans',         'Loans (Liability)'],
    ];

    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $created = 0;
            $skipped = 0;

            foreach (self::GROUPS as [$name, $underName]) {
                $existing = TallyLedgerGroup::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('name', $name)
                    ->exists();

                if ($existing) {
                    $skipped++;
                    continue;
                }

                TallyLedgerGroup::create([
                    'tenant_id'      => $tenant->id,
                    'tally_id'       => null,
                    'alter_id'       => 0,
                    'name'           => $name,
                    'under_id'       => null,
                    'under_name'     => $underName,
                    'is_active'      => true,
                    'last_synced_at' => null,
                ]);

                $created++;
            }

            $this->command->info("TallyStandardGroupsSeeder [{$tenant->name}]: {$created} created, {$skipped} already exist.");
        }
    }
}
