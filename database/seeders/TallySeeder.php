<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TallyAttendanceType;
use App\Models\TallyConnection;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyPayHead;
use App\Models\TallyReport;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyStockItem;
use App\Models\TallyStatutoryMaster;
use App\Models\TallySyncLog;
use App\Models\TallyVoucher;
use App\Models\TallyVoucherInventoryEntry;
use App\Models\TallyVoucherLedgerEntry;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TallySeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('name', 'Tili')->firstOrFail();
        $tid    = $tenant->id;

        // ── Connection ────────────────────────────────────────────────────
        $connection = TallyConnection::firstOrCreate(['tenant_id' => $tid], [
            'company_id'               => 'TILI2024',
            'is_active'                => true,
            'inbound_token'            => Str::random(48),
            'inbound_token_last_used_at' => now()->subHours(2),
            'last_synced_at'           => now()->subMinutes(30),
        ]);

        // ── Ledger Groups ─────────────────────────────────────────────────
        $groups = [];
        $groupData = [
            [1, 1, 'Sundry Debtors',        null,  null,  'Assets',      true,  false, true],
            [2, 2, 'Sundry Creditors',       null,  null,  'Liabilities', false, false, true],
            [3, 3, 'Sales Accounts',         null,  null,  'Income',      true,  true,  true],
            [4, 4, 'Purchase Accounts',      null,  null,  'Expenses',    true,  true,  true],
            [5, 5, 'Bank Accounts',          null,  null,  'Assets',      false, false, false],
            [6, 6, 'Cash-in-Hand',           null,  null,  'Assets',      false, false, false],
            [7, 7, 'Duties & Taxes',         null,  null,  'Liabilities', false, false, true],
            [8, 8, 'Indirect Expenses',      null,  null,  'Expenses',    true,  false, true],
        ];

        foreach ($groupData as [$tid_i, $alterId, $name, $underId, $underName, $nature, $isRev, $affGross, $isAdd]) {
            $groups[$name] = TallyLedgerGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tid_i],
                [
                    'alter_id'        => $alterId,
                    'name'            => $name,
                    'under_id'        => $underId,
                    'under_name'      => $underName,
                    'nature_of_group' => $nature,
                    'is_revenue'      => $isRev,
                    'affects_gross'   => $affGross,
                    'is_addable'      => $isAdd,
                    'is_active'       => true,
                    'last_synced_at'  => now()->subHours(1),
                ]
            );
        }

        // ── Clients & Vendors for mapping ─────────────────────────────────
        $clients = Client::where('tenant_id', $tid)->take(3)->get()->keyBy('name');
        $vendors = Vendor::where('tenant_id', $tid)->take(2)->get()->keyBy('name');

        // ── Ledgers ───────────────────────────────────────────────────────
        $ledgers = [];
        $ledgerData = [
            // Debtors
            [101, 101, 'Rahul Enterprises',   'Sundry Debtors',   'Assets',      'Debtors',   '27AABCP1234D1ZK', null,   'Regular',  'Rahul Sharma',    '9876543210', 'rahul@enterprises.com', '110001', 'Delhi',     'India', 0,   30, 500000,  'Dr', true,  false],
            [102, 102, 'Priya Trading Co.',   'Sundry Debtors',   'Assets',      'Debtors',   '29AACCQ5678E2ZL', null,   'Regular',  'Priya Singh',     '9123456780', 'priya@trading.com',     '560001', 'Karnataka', 'India', 0,   45, 250000,  'Dr', true,  false],
            [103, 103, 'Suresh & Sons',        'Sundry Debtors',   'Assets',      'Debtors',   null,              null,   'Regular',  'Suresh Kumar',    null,         'suresh@sons.com',       '400001', 'Maharashtra', 'India', 0, 30, 100000, 'Dr', true,  false],
            // Creditors
            [201, 201, 'Kapoor Suppliers',     'Sundry Creditors', 'Liabilities', 'Creditors', '07AAFPK9012F3ZM', null,   'Regular',  'Amit Kapoor',     '9871234560', 'kapoor@suppliers.com',  '110002', 'Delhi',     'India', 0,   60, 300000,  'Cr', false, false],
            [202, 202, 'National Goods Ltd',   'Sundry Creditors', 'Liabilities', 'Creditors', '19AABPN3456G4ZN', null,   'Regular',  'Prakash Nair',    '9988776655', 'national@goods.in',     '682001', 'Kerala',    'India', 0,   30, 200000,  'Cr', false, false],
            // Sales & Purchase
            [301, 301, 'Sales - Domestic',     'Sales Accounts',   'Income',      null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Cr', false, false],
            [302, 302, 'Sales - Export',        'Sales Accounts',   'Income',      null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Cr', false, false],
            [401, 401, 'Purchase - Domestic',   'Purchase Accounts','Expenses',    null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Dr', false, false],
            // Bank & Cash
            [501, 501, 'HDFC Bank A/c',         'Bank Accounts',    'Assets',      null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  500000,  'Dr', false, false],
            [601, 601, 'Cash',                  'Cash-in-Hand',     'Assets',      null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  50000,   'Dr', false, false],
            // GST
            [701, 701, 'Output IGST',           'Duties & Taxes',   'Liabilities', null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Cr', false, false],
            [702, 702, 'Input IGST',            'Duties & Taxes',   'Assets',      null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Dr', false, false],
            [703, 703, 'Output CGST',           'Duties & Taxes',   'Liabilities', null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Cr', false, false],
            [704, 704, 'Output SGST',           'Duties & Taxes',   'Liabilities', null,        null,              null,   null,       null,              null,         null,                    null,     null,        null,    0,   0,  0,       'Cr', false, false],
        ];

        foreach ($ledgerData as [
            $tallyId, $alterId, $name, $groupName, $parentGroup, $category,
            $gstin, $pan, $gstType, $contact, $mobile, $email,
            $pin, $state, $country, $creditPeriod, $creditLimit, $openingBal, $openingType,
            $isBillWise, $inventoryAffected
        ]) {
            $mappedClientId = null;
            $mappedVendorId = null;

            if ($groupName === 'Sundry Debtors' && isset($clients[$name])) {
                $mappedClientId = $clients[$name]->id;
            }
            if ($groupName === 'Sundry Creditors') {
                $vendor = $vendors->first();
                if ($vendor) $mappedVendorId = $vendor->id;
            }

            $ledgers[$tallyId] = TallyLedger::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'              => $alterId,
                    'ledger_name'           => $name,
                    'group_name'            => $groupName,
                    'parent_group'          => $parentGroup,
                    'ledger_category'       => $category,
                    'gstin_number'          => $gstin,
                    'pan_number'            => $pan,
                    'gst_type'              => $gstType,
                    'contact_person'        => $contact,
                    'contact_person_mobile' => $mobile,
                    'contact_person_email'  => $email,
                    'pin_code'              => $pin,
                    'state_name'            => $state,
                    'country_name'          => $country,
                    'credit_period'         => $creditPeriod,
                    'credit_limit'          => $creditLimit,
                    'opening_balance'       => $openingBal,
                    'opening_balance_type'  => $openingType,
                    'is_bill_wise_on'       => $isBillWise,
                    'inventory_affected'    => $inventoryAffected,
                    'is_active'             => true,
                    'last_synced_at'        => now()->subHours(1),
                    'mapped_client_id'      => $mappedClientId,
                    'mapped_vendor_id'      => $mappedVendorId,
                    'addresses'             => $pin ? [['address' => '123 Main Road', 'state' => $state, 'country' => $country, 'pincode' => $pin]] : null,
                ]
            );
        }

        // ── Stock Groups ──────────────────────────────────────────────────
        $stockGroups = [];
        $stockGroupData = [
            [1001, 1001, 'Electronics',       null, null,       'Stock-in-Hand', true],
            [1002, 1002, 'Furniture',         null, null,       'Stock-in-Hand', true],
            [1003, 1003, 'Office Supplies',   null, null,       'Stock-in-Hand', false],
            [1004, 1004, 'Laptops',           1001, 'Electronics', 'Stock-in-Hand', true],
            [1005, 1005, 'Mobile Phones',     1001, 'Electronics', 'Stock-in-Hand', true],
        ];

        foreach ($stockGroupData as [$tallyId, $alterId, $name, $parentId, $parentName, $nature, $addQty]) {
            $stockGroups[$tallyId] = TallyStockGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'              => $alterId,
                    'name'                  => $name,
                    'parent_id'             => $parentId,
                    'parent_name'           => $parentName,
                    'nature_of_group'       => $nature,
                    'should_add_quantities' => $addQty,
                    'is_active'             => true,
                    'last_synced_at'        => now()->subHours(1),
                ]
            );
        }

        // ── Stock Categories ──────────────────────────────────────────────
        $stockCategories = [];
        $catData = [
            [2001, 2001, 'Category A - Premium',  null],
            [2002, 2002, 'Category B - Standard', null],
            [2003, 2003, 'Category C - Budget',   null],
        ];

        foreach ($catData as [$tallyId, $alterId, $name, $parentName]) {
            $stockCategories[$tallyId] = TallyStockCategory::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'       => $alterId,
                    'name'           => $name,
                    'parent_name'    => $parentName,
                    'is_active'      => true,
                    'last_synced_at' => now()->subHours(1),
                ]
            );
        }

        // ── Stock Items ───────────────────────────────────────────────────
        $products = Product::where('tenant_id', $tid)->take(5)->get();
        $productList = $products->values();

        $stockItems = [];
        $itemData = [
            [3001, 3001, 'Dell Laptop 15"',       1004, 'Laptops',       2001, 'Category A - Premium',  'Nos', 18, 18, 9,  9,  '84713019', 75000,  70000,  80000,  10, 70000, 700000],
            [3002, 3002, 'HP Laptop 14"',          1004, 'Laptops',       2002, 'Category B - Standard', 'Nos', 18, 18, 9,  9,  '84713019', 60000,  55000,  65000,  5,  55000, 275000],
            [3003, 3003, 'Samsung Galaxy S24',     1005, 'Mobile Phones', 2001, 'Category A - Premium',  'Nos', 18, 18, 9,  9,  '85171200', 80000,  75000,  85000,  15, 75000, 1125000],
            [3004, 3004, 'iPhone 15',              1005, 'Mobile Phones', 2001, 'Category A - Premium',  'Nos', 18, 18, 9,  9,  '85171200', 90000,  85000,  95000,  8,  85000, 680000],
            [3005, 3005, 'Office Chair',           1002, 'Furniture',     2002, 'Category B - Standard', 'Nos', 18, 18, 9,  9,  '94017100', 12000,  10000,  15000,  20, 10000, 200000],
            [3006, 3006, 'Standing Desk',          1002, 'Furniture',     2002, 'Category B - Standard', 'Nos', 18, 18, 9,  9,  '94031090', 25000,  22000,  28000,  10, 22000, 220000],
            [3007, 3007, 'A4 Paper (Ream)',        1003, 'Office Supplies',2003, 'Category C - Budget',   'Pkt', 12, 12, 6,  6,  '48025590', 500,    450,    550,    100, 450,  45000],
            [3008, 3008, 'Printer Cartridge',      1003, 'Office Supplies',2003, 'Category C - Budget',   'Nos', 18, 18, 9,  9,  '84439910', 1500,   1200,   1800,   50, 1200, 60000],
            [3009, 3009, 'USB-C Hub',              1001, 'Electronics',   2002, 'Category B - Standard', 'Nos', 18, 18, 9,  9,  '85176900', 3500,   3000,   4000,   30, 3000, 90000],
            [3010, 3010, 'Wireless Keyboard',      1001, 'Electronics',   2002, 'Category B - Standard', 'Nos', 18, 18, 9,  9,  '84716060', 2500,   2000,   3000,   25, 2000, 50000],
        ];

        foreach ($itemData as $i => [
            $tallyId, $alterId, $name, $stockGroupId, $stockGroupName,
            $stockCatId, $categoryName, $unit,
            $igst, $cgst, $sgst, $cess, $hsn,
            $mrp, $stdCost, $stdPrice,
            $openQty, $openRate, $openValue
        ]) {
            $mappedProductId = $productList->get($i)?->id;

            $stockItems[$tallyId] = TallyStockItem::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'          => $alterId,
                    'name'              => $name,
                    'stock_group_id'    => $stockGroupId,
                    'stock_group_name'  => $stockGroupName,
                    'stock_category_id' => $stockCatId,
                    'category_name'     => $categoryName,
                    'unit_name'         => $unit,
                    'is_gst_applicable' => true,
                    'taxability'        => 'Taxable',
                    'calculation_type'  => 'On Value',
                    'igst_rate'         => $igst,
                    'cgst_rate'         => $cgst,
                    'sgst_rate'         => $sgst,
                    'cess_rate'         => $cess,
                    'hsn_code'          => $hsn,
                    'mrp_rate'          => $mrp,
                    'standard_cost'     => $stdCost,
                    'standard_price'    => $stdPrice,
                    'opening_balance'   => $openQty,
                    'opening_rate'      => $openRate,
                    'opening_value'     => $openValue,
                    'closing_balance'   => $openQty - rand(1, 5),
                    'closing_rate'      => $openRate,
                    'closing_value'     => ($openQty - rand(1, 5)) * $openRate,
                    'costing_method'    => 'FIFO',
                    'is_active'         => true,
                    'last_synced_at'    => now()->subHours(1),
                    'mapped_product_id' => $mappedProductId,
                ]
            );
        }

        // ── Vouchers ──────────────────────────────────────────────────────
        $voucherData = [
            // [tally_id, alter_id, type, number, date, party_ledger_id, total, is_invoice, narration]
            [4001, 4001, 'Sales',        'SV/2024/001', '2024-04-01', 101, 94400,  true,  'Sale of Dell Laptops to Rahul Enterprises'],
            [4002, 4002, 'Sales',        'SV/2024/002', '2024-04-05', 102, 47200,  true,  'Sale of Office Chairs to Priya Trading Co.'],
            [4003, 4003, 'Purchase',     'PV/2024/001', '2024-04-02', 201, 70800,  true,  'Purchase of Laptops from Kapoor Suppliers'],
            [4004, 4004, 'Purchase',     'PV/2024/002', '2024-04-08', 202, 35400,  true,  'Purchase of Mobile Phones from National Goods Ltd'],
            [4005, 4005, 'Receipt',      'RC/2024/001', '2024-04-10', 101, 94400,  false, 'Payment received from Rahul Enterprises against SV/2024/001'],
            [4006, 4006, 'Payment',      'PY/2024/001', '2024-04-12', 201, 70800,  false, 'Payment made to Kapoor Suppliers against PV/2024/001'],
            [4007, 4007, 'Credit Note',  'CN/2024/001', '2024-04-15', 102, 11800,  true,  'Return of defective chair by Priya Trading Co.'],
            [4008, 4008, 'Journal',      'JV/2024/001', '2024-04-18', 702, 5000,   false, 'Input GST adjustment entry'],
            [4009, 4009, 'Contra',       'CT/2024/001', '2024-04-20', 601, 20000,  false, 'Cash withdrawn from HDFC Bank'],
            [4010, 4010, 'Debit Note',   'DN/2024/001', '2024-04-22', 201, 3540,   true,  'Debit note raised on Kapoor Suppliers for quality issue'],
        ];

        $vouchers = [];
        foreach ($voucherData as [$tallyId, $alterId, $type, $number, $date, $partyLedgerId, $total, $isInvoice, $narration]) {
            $partyLedger = $ledgers[$partyLedgerId] ?? null;

            $vouchers[$tallyId] = TallyVoucher::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'              => $alterId,
                    'voucher_type'          => $type,
                    'voucher_number'        => $number,
                    'voucher_date'          => $date,
                    'party_name'            => $partyLedger?->ledger_name,
                    'party_tally_ledger_id' => $partyLedger?->id,
                    'voucher_total'         => $total,
                    'is_invoice'            => $isInvoice,
                    'is_deleted'            => false,
                    'place_of_supply'       => 'Delhi',
                    'narration'             => $narration,
                    'buyer_name'            => $partyLedger?->mailing_name ?? $partyLedger?->ledger_name,
                    'buyer_gstin'           => $partyLedger?->gstin_number,
                    'buyer_state'           => $partyLedger?->state_name,
                    'buyer_country'         => $partyLedger?->country_name ?? 'India',
                    'is_active'             => true,
                    'last_synced_at'        => now()->subMinutes(30),
                ]
            );
        }

        // ── Inventory Entries (for Sales and Purchase vouchers) ───────────
        $inventoryEntryData = [
            // [voucher_tally_id, stock_item_tally_id, qty, rate, amount, igst, tax_amount]
            [4001, 3001, 1, 80000, 80000, 18, 14400],
            [4002, 3005, 2, 10000, 20000, 18,  3600],
            [4003, 3001, 1, 60000, 60000, 18, 10800],
            [4004, 3003, 1, 75000, 30000, 18,  5400],
            [4007, 3005, 1, 10000, 10000, 18,  1800],
        ];

        foreach ($inventoryEntryData as [$voucherId, $stockItemId, $qty, $rate, $amount, $igstRate, $taxAmount]) {
            $voucher   = $vouchers[$voucherId] ?? null;
            $stockItem = $stockItems[$stockItemId] ?? null;
            if (!$voucher || !$stockItem) continue;

            TallyVoucherInventoryEntry::firstOrCreate(
                ['tally_voucher_id' => $voucher->id, 'tally_stock_item_id' => $stockItem->id],
                [
                    'tenant_id'        => $tid,
                    'stock_item_name'  => $stockItem->name,
                    'hsn_code'         => $stockItem->hsn_code,
                    'unit'             => $stockItem->unit_name,
                    'igst_rate'        => $igstRate,
                    'is_deemed_positive' => true,
                    'actual_qty'       => $qty,
                    'billed_qty'       => $qty,
                    'rate'             => $rate,
                    'discount_percent' => 0,
                    'amount'           => $amount,
                    'tax_amount'       => $taxAmount,
                    'sales_ledger'     => 'Sales - Domestic',
                ]
            );
        }

        // ── Ledger Entries (for all vouchers) ─────────────────────────────
        $ledgerEntryData = [
            // [voucher_tally_id, ledger_tally_id, amount, is_deemed_positive, is_party]
            [4001, 101, 94400,  true,  true],   // Debtor Dr
            [4001, 301, -80000, false, false],  // Sales Cr
            [4001, 701, -14400, false, false],  // Output IGST Cr

            [4002, 102, 23600,  true,  true],
            [4002, 301, -20000, false, false],
            [4002, 701, -3600,  false, false],

            [4003, 401, 60000,  true,  false],  // Purchase Dr
            [4003, 702, 10800,  true,  false],  // Input IGST Dr
            [4003, 201, -70800, false, true],   // Creditor Cr

            [4004, 401, 30000,  true,  false],
            [4004, 702, 5400,   true,  false],
            [4004, 202, -35400, false, true],

            [4005, 501, 94400,  true,  false],  // Bank Dr
            [4005, 101, -94400, false, true],   // Debtor Cr

            [4006, 201, 70800,  true,  true],   // Creditor Dr
            [4006, 501, -70800, false, false],  // Bank Cr

            [4007, 102, -11800, false, true],
            [4007, 301, 10000,  true,  false],
            [4007, 701, 1800,   true,  false],

            [4008, 702, 5000,   true,  false],
            [4008, 701, -5000,  false, false],

            [4009, 601, 20000,  true,  false],  // Cash Dr
            [4009, 501, -20000, false, false],  // Bank Cr

            [4010, 401, 3000,   true,  false],
            [4010, 702, 540,    true,  false],
            [4010, 201, -3540,  false, true],
        ];

        foreach ($ledgerEntryData as [$voucherId, $ledgerId, $amount, $isDeemedPositive, $isParty]) {
            $voucher = $vouchers[$voucherId] ?? null;
            $ledger  = $ledgers[$ledgerId]  ?? null;
            if (!$voucher || !$ledger) continue;

            TallyVoucherLedgerEntry::firstOrCreate(
                ['tally_voucher_id' => $voucher->id, 'tally_ledger_id' => $ledger->id],
                [
                    'tenant_id'          => $tid,
                    'ledger_name'        => $ledger->ledger_name,
                    'ledger_group'       => $ledger->group_name,
                    'ledger_amount'      => $amount,
                    'is_deemed_positive' => $isDeemedPositive,
                    'is_party_ledger'    => $isParty,
                ]
            );
        }

        // ── Reports ───────────────────────────────────────────────────────
        $reportData = [
            ['trial_balance',     '2024-04-01', '2024-04-30', ['debit_total' => 825400, 'credit_total' => 825400, 'accounts' => 14]],
            ['profit_loss',       '2024-04-01', '2024-04-30', ['gross_profit' => 80000, 'net_profit' => 70000, 'sales' => 100000, 'purchase' => 90000]],
            ['balance_sheet',     '2024-04-01', '2024-04-30', ['total_assets' => 1200000, 'total_liabilities' => 800000, 'net_worth' => 400000]],
            ['sales_register',    '2024-04-01', '2024-04-30', ['total_sales' => 117600, 'total_tax' => 18000, 'net_sales' => 100000, 'voucher_count' => 2]],
            ['purchase_register', '2024-04-01', '2024-04-30', ['total_purchase' => 106200, 'total_tax' => 16200, 'net_purchase' => 90000, 'voucher_count' => 2]],
        ];

        foreach ($reportData as [$type, $from, $to, $data]) {
            TallyReport::firstOrCreate(
                ['tenant_id' => $tid, 'report_type' => $type, 'period_to' => $to],
                [
                    'period_from'  => $from,
                    'data'         => $data,
                    'generated_at' => now()->subHours(2),
                    'synced_at'    => now()->subHours(2),
                ]
            );
        }

        // ── Sync Logs ─────────────────────────────────────────────────────
        $logData = [
            ['ledger_groups',       'inbound', 'success', false, 8,  0, 0, 0, null],
            ['ledgers',             'inbound', 'success', false, 14, 0, 0, 0, null],
            ['stock_groups',        'inbound', 'success', false, 5,  0, 0, 0, null],
            ['stock_categories',    'inbound', 'success', false, 3,  0, 0, 0, null],
            ['stock_items',         'inbound', 'success', false, 10, 0, 0, 0, null],
            ['vouchers_sales',      'inbound', 'success', false, 2,  0, 0, 0, null],
            ['vouchers_purchase',   'inbound', 'success', false, 2,  0, 0, 0, null],
            ['vouchers_receipt',    'inbound', 'success', false, 1,  0, 0, 0, null],
            ['vouchers_payment',    'inbound', 'success', false, 1,  0, 0, 0, null],
            ['vouchers_creditnote', 'inbound', 'success', false, 1,  0, 0, 0, null],
            ['vouchers_journal',    'inbound', 'success', false, 1,  0, 0, 0, null],
            ['vouchers_contra',     'inbound', 'success', false, 1,  0, 0, 0, null],
            ['vouchers_debitnote',  'inbound', 'success', false, 1,  0, 0, 0, null],
            // Second sync run (some updates)
            ['ledger_groups',       'inbound', 'success', false, 0,  2, 6, 0, null],
            ['ledgers',             'inbound', 'success', false, 0,  3, 11,0, null],
            ['stock_items',         'inbound', 'success', false, 0,  4, 6, 0, null],
            ['vouchers_sales',      'inbound', 'success', false, 1,  1, 0, 0, null],
            ['vouchers_purchase',   'inbound', 'success', false, 0,  2, 0, 0, null],
            // Manual trigger
            ['manual_trigger',      'inbound', 'success', true,  0,  0, 0, 0, null],
            // Failed attempt
            ['ledgers',             'inbound', 'failed',  false, 0,  0, 0, 3, 'Ledger "XYZ Corp" has duplicate tally_id 14 for this tenant'],
        ];

        foreach ($logData as $i => [$entity, $direction, $status, $manual, $created, $updated, $skipped, $failed, $error]) {
            TallySyncLog::create([
                'tenant_id'         => $tid,
                'entity'            => $entity,
                'direction'         => $direction,
                'status'            => $status,
                'triggered_manually' => $manual,
                'records_created'   => $created,
                'records_updated'   => $updated,
                'records_skipped'   => $skipped,
                'records_failed'    => $failed,
                'error_message'     => $error,
                'started_at'        => now()->subDays(1)->addMinutes($i * 3),
                'completed_at'      => $status === 'failed' ? null : now()->subDays(1)->addMinutes($i * 3 + 1),
            ]);
        }

        // ── Statutory Masters ─────────────────────────────────────────────
        $statutoryData = [
            // [tally_id, alter_id, name, type, reg_number, state_code, reg_type, pan, tan, applicable_from, details]
            [5001, 5001, 'GST Registration - Delhi',       'GST', '07AABCT1234A1ZK', '07', 'Regular',      'AABCT1234A', null,         '2017-07-01', ['GSTRate' => 18, 'FilingFrequency' => 'Monthly']],
            [5002, 5002, 'GST Registration - Karnataka',   'GST', '29AABCT1234A2ZK', '29', 'Regular',      'AABCT1234A', null,         '2017-07-01', ['GSTRate' => 18, 'FilingFrequency' => 'Monthly']],
            [5003, 5003, 'TDS - Section 194C (Contractor)','TDS', 'TDS-194C',         null, 'TDS Deductor', null,         'DELT12345A', '2023-04-01', ['Section' => '194C', 'Rate' => 1, 'ThresholdLimit' => 30000]],
            [5004, 5004, 'TDS - Section 194J (Prof. Svc)', 'TDS', 'TDS-194J',         null, 'TDS Deductor', null,         'DELT12345A', '2023-04-01', ['Section' => '194J', 'Rate' => 10, 'ThresholdLimit' => 30000]],
            [5005, 5005, 'TCS - Section 206C',             'TCS', 'TCS-206C',         null, 'TCS Collector',null,         'DELT12345A', '2023-04-01', ['Section' => '206C', 'Rate' => 0.1]],
            [5006, 5006, 'PF Registration',                'PF',  'PF/DL/12345/001',  null, 'Employer',     null,         null,         '2020-01-01', ['EmployerRate' => 12, 'EmployeeRate' => 12, 'AdminCharge' => 0.5]],
            [5007, 5007, 'ESI Registration',               'ESI', 'ESI/31/12345/001', null, 'Employer',     null,         null,         '2020-01-01', ['EmployerRate' => 3.25, 'EmployeeRate' => 0.75]],
            [5008, 5008, 'Professional Tax - Delhi',       'PT',  'PT/DL/2024/001',   '07', 'PT Deductor',  null,         null,         '2023-04-01', ['MonthlyLimit' => 15000, 'TaxAmount' => 200]],
        ];

        foreach ($statutoryData as [$tallyId, $alterId, $name, $type, $regNo, $stateCode, $regType, $pan, $tan, $appFrom, $details]) {
            TallyStatutoryMaster::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'            => $alterId,
                    'name'                => $name,
                    'statutory_type'      => $type,
                    'registration_number' => $regNo,
                    'state_code'          => $stateCode,
                    'registration_type'   => $regType,
                    'pan'                 => $pan,
                    'tan'                 => $tan,
                    'applicable_from'     => $appFrom,
                    'details'             => $details,
                    'is_active'           => true,
                    'last_synced_at'      => now()->subHours(1),
                ]
            );
        }

        // ── Employee Groups ───────────────────────────────────────────────
        $empGroupData = [
            [6001, 6001, 'Management',          null],
            [6002, 6002, 'Operations',          null],
            [6003, 6003, 'Sales & Marketing',   null],
            [6004, 6004, 'Technology',          null],
            [6005, 6005, 'Finance & Accounts',  null],
        ];

        $empGroups = [];
        foreach ($empGroupData as [$tallyId, $alterId, $name, $parentName]) {
            $empGroups[$tallyId] = TallyEmployeeGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'       => $alterId,
                    'name'           => $name,
                    'parent_name'    => $parentName,
                    'is_active'      => true,
                    'last_synced_at' => now()->subHours(1),
                ]
            );
        }

        // ── Pay Heads ─────────────────────────────────────────────────────
        $payHeadData = [
            // [tally_id, alter_id, name, type, pay_slip_name, under_group, ledger_name, calc_type, rate, rate_period]
            [7001, 7001, 'Basic Salary',              'Earning',                       'Basic',       'Salary Payable', 'Basic Salary Ledger',     'On Attendance',    null,  'Monthly'],
            [7002, 7002, 'House Rent Allowance',      'Earning',                       'HRA',         'Salary Payable', 'HRA Ledger',              'As Computed Value',40,    'Monthly'],
            [7003, 7003, 'Conveyance Allowance',      'Earning',                       'Conveyance',  'Salary Payable', 'Conveyance Ledger',        'Fixed',            1600,  'Monthly'],
            [7004, 7004, 'Special Allowance',         'Earning',                       'Spl Allowance','Salary Payable','Special Allowance Ledger', 'As Computed Value',null,  'Monthly'],
            [7005, 7005, 'PF - Employee',             'Employees\' Statutory Deductions','PF Emp',   'PF Payable',     'PF Employee Ledger',       'As Computed Value',12,    'Monthly'],
            [7006, 7006, 'PF - Employer',             'Employer\'s Statutory Contributions','PF Emp\'s','PF Payable',  'PF Employer Ledger',       'As Computed Value',12,    'Monthly'],
            [7007, 7007, 'ESI - Employee',            'Employees\' Statutory Deductions','ESI Emp',  'ESI Payable',    'ESI Employee Ledger',      'As Computed Value',0.75,  'Monthly'],
            [7008, 7008, 'ESI - Employer',            'Employer\'s Statutory Contributions','ESI Emp\'s','ESI Payable', 'ESI Employer Ledger',     'As Computed Value',3.25,  'Monthly'],
            [7009, 7009, 'TDS on Salary',             'Employees\' Statutory Deductions','TDS',      'TDS Payable',    'TDS Salary Ledger',        'As Computed Value',null,  'Monthly'],
            [7010, 7010, 'Professional Tax',          'Employees\' Statutory Deductions','PT',       'PT Payable',     'Professional Tax Ledger',  'Fixed',            200,   'Monthly'],
        ];

        foreach ($payHeadData as [$tallyId, $alterId, $name, $type, $slipName, $underGroup, $ledgerName, $calcType, $rate, $ratePeriod]) {
            TallyPayHead::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'         => $alterId,
                    'name'             => $name,
                    'pay_head_type'    => $type,
                    'pay_slip_name'    => $slipName,
                    'under_group'      => $underGroup,
                    'ledger_name'      => $ledgerName,
                    'calculation_type' => $calcType,
                    'rate'             => $rate,
                    'rate_period'      => $ratePeriod,
                    'is_active'        => true,
                    'last_synced_at'   => now()->subHours(1),
                ]
            );
        }

        // ── Attendance Types ──────────────────────────────────────────────
        $attendanceData = [
            [8001, 8001, 'Present',         'Attendance',              'Days'],
            [8002, 8002, 'Casual Leave',    'Leave with Pay',          'Days'],
            [8003, 8003, 'Sick Leave',      'Leave with Pay',          'Days'],
            [8004, 8004, 'Unpaid Leave',    'Leave without Pay',       'Days'],
            [8005, 8005, 'Holiday',         'Attendance',              'Days'],
            [8006, 8006, 'Week Off',        'Attendance',              'Days'],
            [8007, 8007, 'Overtime Hours',  'Productivity',            'Hours'],
        ];

        foreach ($attendanceData as [$tallyId, $alterId, $name, $type, $uom]) {
            TallyAttendanceType::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'        => $alterId,
                    'name'            => $name,
                    'attendance_type' => $type,
                    'unit_of_measure' => $uom,
                    'is_active'       => true,
                    'last_synced_at'  => now()->subHours(1),
                ]
            );
        }

        // ── Employees ─────────────────────────────────────────────────────
        $employeeData = [
            // [tally_id, alter_id, name, emp_no, group_id, designation, dept, doj, dob, gender, pan, pf, uan, esi, bank, acct, ifsc]
            [9001, 9001, 'Arjun Mehta',      'EMP001', 6001, 'Director',         'Management',         '2019-06-01', '1980-03-15', 'Male',   'ABCPM1234E', 'PF/001', 'UAN001234567', null,         'HDFC Bank', '50100012345678', 'HDFC0001234'],
            [9002, 9002, 'Sunita Sharma',    'EMP002', 6004, 'Senior Developer', 'Technology',         '2021-01-15', '1992-07-22', 'Female', 'BCDPS5678F', 'PF/002', 'UAN001234568', 'ESI0001',    'ICICI Bank','012345678901',   'ICIC0001234'],
            [9003, 9003, 'Rakesh Gupta',     'EMP003', 6003, 'Sales Manager',    'Sales & Marketing',  '2020-08-10', '1988-11-05', 'Male',   'CDEPT9012G', 'PF/003', 'UAN001234569', 'ESI0002',    'SBI',       '30012345678',    'SBIN0001234'],
            [9004, 9004, 'Pooja Nair',       'EMP004', 6005, 'Accountant',       'Finance & Accounts', '2022-03-01', '1995-04-18', 'Female', 'DEPQN3456H', 'PF/004', 'UAN001234570', 'ESI0003',    'HDFC Bank', '50100087654321', 'HDFC0005678'],
            [9005, 9005, 'Vikram Singh',     'EMP005', 6002, 'Operations Lead',  'Operations',         '2020-11-20', '1990-09-30', 'Male',   'EFPVS7890I', 'PF/005', 'UAN001234571', 'ESI0004',    'Kotak Bank','1234567890',     'KKBK0001234'],
        ];

        foreach ($employeeData as [$tallyId, $alterId, $name, $empNo, $groupId, $designation, $dept, $doj, $dob, $gender, $pan, $pfNo, $uan, $esi, $bank, $acct, $ifsc]) {
            TallyEmployee::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'           => $alterId,
                    'name'               => $name,
                    'employee_number'    => $empNo,
                    'group_name'         => $empGroups[$groupId]?->name,
                    'designation'        => $designation,
                    'department'         => $dept,
                    'date_of_joining'    => $doj,
                    'date_of_birth'      => $dob,
                    'gender'             => $gender,
                    'pan'                => $pan,
                    'pf_number'          => $pfNo,
                    'uan_number'         => $uan,
                    'esi_number'         => $esi,
                    'bank_name'          => $bank,
                    'bank_account_number'=> $acct,
                    'bank_ifsc'          => $ifsc,
                    'addresses'          => [['address' => 'Delhi NCR', 'state' => 'Delhi', 'country' => 'India']],
                    'salary_details'     => ['BasicPercent' => 50, 'HRAPercent' => 40, 'PFApplicable' => true],
                    'is_active'          => true,
                    'last_synced_at'     => now()->subHours(1),
                ]
            );
        }

        $this->command->info('TallySeeder: seeded connection, 8 ledger groups, 14 ledgers, 5 stock groups, 3 categories, 10 stock items, 10 vouchers, reports, sync logs for Tili.');
    }
}
