<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TallyAttendanceType;
use App\Models\TallyCompany;
use App\Models\TallyConnection;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyGodown;
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
use App\Models\TallyVoucherEmployeeAllocation;
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

        // ── Company ───────────────────────────────────────────────────────
        TallyCompany::firstOrCreate(
            ['tally_connection_id' => $connection->id],
            [
                'company_guid'   => 'TILI-GUID-2024-001',
                'company_name'   => 'Tili Pvt Ltd',
                'licence_type'   => 'Silver',
                'licence_number' => 'TILI-LIC-2024',
            ]
        );

        // ── Ledger Groups ─────────────────────────────────────────────────
        $groups = [];
        $groupData = [
            [1, 1, 'Sundry Debtors',        null, null],
            [2, 2, 'Sundry Creditors',       null, null],
            [3, 3, 'Sales Accounts',         null, null],
            [4, 4, 'Purchase Accounts',      null, null],
            [5, 5, 'Bank Accounts',          null, null],
            [6, 6, 'Cash-in-Hand',           null, null],
            [7, 7, 'Duties & Taxes',         null, null],
            [8, 8, 'Indirect Expenses',      null, null],
        ];

        foreach ($groupData as [$tid_i, $alterId, $name, $underId, $underName]) {
            $groups[$name] = TallyLedgerGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tid_i],
                [
                    'alter_id'        => $alterId,
                    'name'            => $name,
                    'under_id'        => $underId,
                    'under_name'      => $underName,
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
            // [tallyId, alterId, name, groupName, category, gstin, gstType, contact, mobile, email, pin, state, country, creditPeriod, creditLimit, openingBal, openingType, isBillWise, inventoryAffected]
            [101, 101, 'Rahul Enterprises',   'Sundry Debtors',   'Debtors',   '27AABCP1234D1ZK', 'Regular', 'Rahul Sharma',  '9876543210', 'rahul@enterprises.com', '110001', 'Delhi',       'India', 30, 500000, 0,      'Dr', true,  false],
            [102, 102, 'Priya Trading Co.',   'Sundry Debtors',   'Debtors',   '29AACCQ5678E2ZL', 'Regular', 'Priya Singh',   '9123456780', 'priya@trading.com',     '560001', 'Karnataka',   'India', 45, 250000, 0,      'Dr', true,  false],
            [103, 103, 'Suresh & Sons',        'Sundry Debtors',   'Debtors',   null,              'Regular', 'Suresh Kumar',  null,         'suresh@sons.com',       '400001', 'Maharashtra', 'India', 30, 100000, 0,      'Dr', true,  false],
            [201, 201, 'Kapoor Suppliers',     'Sundry Creditors', 'Creditors', '07AAFPK9012F3ZM', 'Regular', 'Amit Kapoor',   '9871234560', 'kapoor@suppliers.com',  '110002', 'Delhi',       'India', 60, 300000, 0,      'Cr', false, false],
            [202, 202, 'National Goods Ltd',   'Sundry Creditors', 'Creditors', '19AABPN3456G4ZN', 'Regular', 'Prakash Nair',  '9988776655', 'national@goods.in',     '682001', 'Kerala',      'India', 30, 200000, 0,      'Cr', false, false],
            [301, 301, 'Sales - Domestic',     'Sales Accounts',   null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Cr', false, false],
            [302, 302, 'Sales - Export',        'Sales Accounts',   null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Cr', false, false],
            [401, 401, 'Purchase - Domestic',   'Purchase Accounts',null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Dr', false, false],
            [501, 501, 'HDFC Bank A/c',         'Bank Accounts',    null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      500000, 'Dr', false, false],
            [601, 601, 'Cash',                  'Cash-in-Hand',     null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      50000,  'Dr', false, false],
            [701, 701, 'Output IGST',           'Duties & Taxes',   null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Cr', false, false],
            [702, 702, 'Input IGST',            'Duties & Taxes',   null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Dr', false, false],
            [703, 703, 'Output CGST',           'Duties & Taxes',   null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Cr', false, false],
            [704, 704, 'Output SGST',           'Duties & Taxes',   null,        null,              null,      null,            null,         null,                    null,     null,          null,    0,  0,      0,      'Cr', false, false],
        ];

        foreach ($ledgerData as [
            $tallyId, $alterId, $name, $groupName, $category,
            $gstin, $gstType, $contact, $mobile, $email,
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
                    'ledger_category'       => $category,
                    'gstin_number'          => $gstin,
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
            [1001, 1001, 'Electronics',     null],
            [1002, 1002, 'Furniture',       null],
            [1003, 1003, 'Office Supplies', null],
            [1004, 1004, 'Laptops',         'Electronics'],
            [1005, 1005, 'Mobile Phones',   'Electronics'],
        ];

        foreach ($stockGroupData as [$tallyId, $alterId, $name, $parent]) {
            $stockGroups[$tallyId] = TallyStockGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'       => $alterId,
                    'name'           => $name,
                    'parent'         => $parent,
                    'is_active'      => true,
                    'last_synced_at' => now()->subHours(1),
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

        foreach ($catData as [$tallyId, $alterId, $name, $parent]) {
            $stockCategories[$tallyId] = TallyStockCategory::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'       => $alterId,
                    'name'           => $name,
                    'parent'         => $parent,
                    'is_active'      => true,
                    'last_synced_at' => now()->subHours(1),
                ]
            );
        }

        // ── Godowns ───────────────────────────────────────────────────────
        $godownData = [
            [1, 1, 'Main Godown',      null,          'GUID-GDN-001'],
            [2, 2, 'Secondary Godown', 'Main Godown', 'GUID-GDN-002'],
        ];

        foreach ($godownData as [$tallyId, $alterId, $name, $under, $guid]) {
            TallyGodown::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'       => $alterId,
                    'guid'           => $guid,
                    'name'           => $name,
                    'under'          => $under,
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
            // [tallyId, alterId, name, stockGroupId, stockGroupName, stockCatId, categoryName, unit, igst, cgst, sgst, cess, hsn, mrp, openQty, openRate, openValue]
            [3001, 3001, 'Dell Laptop 15"',       1004, 'Laptops',        2001, 'Category A - Premium',  'Nos', 18, 9,  9,  0, '84713019', 80000, 10, 70000, 700000],
            [3002, 3002, 'HP Laptop 14"',          1004, 'Laptops',        2002, 'Category B - Standard', 'Nos', 18, 9,  9,  0, '84713019', 65000, 5,  55000, 275000],
            [3003, 3003, 'Samsung Galaxy S24',     1005, 'Mobile Phones',  2001, 'Category A - Premium',  'Nos', 18, 9,  9,  0, '85171200', 85000, 15, 75000, 1125000],
            [3004, 3004, 'iPhone 15',              1005, 'Mobile Phones',  2001, 'Category A - Premium',  'Nos', 18, 9,  9,  0, '85171200', 95000, 8,  85000, 680000],
            [3005, 3005, 'Office Chair',           1002, 'Furniture',      2002, 'Category B - Standard', 'Nos', 18, 9,  9,  0, '94017100', 15000, 20, 10000, 200000],
            [3006, 3006, 'Standing Desk',          1002, 'Furniture',      2002, 'Category B - Standard', 'Nos', 18, 9,  9,  0, '94031090', 28000, 10, 22000, 220000],
            [3007, 3007, 'A4 Paper (Ream)',        1003, 'Office Supplies', 2003, 'Category C - Budget',   'Pkt', 12, 6,  6,  0, '48025590', 550,   100, 450,  45000],
            [3008, 3008, 'Printer Cartridge',      1003, 'Office Supplies', 2003, 'Category C - Budget',   'Nos', 18, 9,  9,  0, '84439910', 1800, 50, 1200, 60000],
            [3009, 3009, 'USB-C Hub',              1001, 'Electronics',    2002, 'Category B - Standard', 'Nos', 18, 9,  9,  0, '85176900', 4000,  30, 3000, 90000],
            [3010, 3010, 'Wireless Keyboard',      1001, 'Electronics',    2002, 'Category B - Standard', 'Nos', 18, 9,  9,  0, '84716060', 3000,  25, 2000, 50000],
        ];

        foreach ($itemData as $i => [
            $tallyId, $alterId, $name, $stockGroupId, $stockGroupName,
            $stockCatId, $categoryName, $unit,
            $igst, $cgst, $sgst, $cess, $hsn,
            $mrp, $openQty, $openRate, $openValue
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
                    'opening_balance'   => $openQty,
                    'opening_rate'      => $openRate,
                    'opening_value'     => $openValue,
                    'closing_balance'   => $openQty - rand(1, 5),
                    'closing_rate'      => $openRate,
                    'closing_value'     => ($openQty - rand(1, 5)) * $openRate,
                    'is_active'         => true,
                    'last_synced_at'    => now()->subHours(1),
                    'mapped_product_id' => $mappedProductId,
                ]
            );
        }

        // ── Vouchers ──────────────────────────────────────────────────────
        $voucherData = [
            [4001, 4001, 'Sales',       'SV/2024/001', '2024-04-01', 101, 94400,  true,  'Sale of Dell Laptops to Rahul Enterprises'],
            [4002, 4002, 'Sales',       'SV/2024/002', '2024-04-05', 102, 47200,  true,  'Sale of Office Chairs to Priya Trading Co.'],
            [4003, 4003, 'Purchase',    'PV/2024/001', '2024-04-02', 201, 70800,  true,  'Purchase of Laptops from Kapoor Suppliers'],
            [4004, 4004, 'Purchase',    'PV/2024/002', '2024-04-08', 202, 35400,  true,  'Purchase of Mobile Phones from National Goods Ltd'],
            [4005, 4005, 'Receipt',     'RC/2024/001', '2024-04-10', 101, 94400,  false, 'Payment received from Rahul Enterprises against SV/2024/001'],
            [4006, 4006, 'Payment',     'PY/2024/001', '2024-04-12', 201, 70800,  false, 'Payment made to Kapoor Suppliers against PV/2024/001'],
            [4007, 4007, 'Credit Note', 'CN/2024/001', '2024-04-15', 102, 11800,  true,  'Return of defective chair by Priya Trading Co.'],
            [4008, 4008, 'Journal',     'JV/2024/001', '2024-04-18', 702, 5000,   false, 'Input GST adjustment entry'],
            [4009, 4009, 'Contra',      'CT/2024/001', '2024-04-20', 601, 20000,  false, 'Cash withdrawn from HDFC Bank'],
            [4010, 4010, 'Debit Note',  'DN/2024/001', '2024-04-22', 201, 3540,   true,  'Debit note raised on Kapoor Suppliers for quality issue'],
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
                    'buyer_name'            => $partyLedger?->ledger_name,
                    'buyer_gstin'           => $partyLedger?->gstin_number,
                    'buyer_state'           => $partyLedger?->state_name,
                    'buyer_country'         => $partyLedger?->country_name ?? 'India',
                    'is_active'             => true,
                    'last_synced_at'        => now()->subMinutes(30),
                ]
            );
        }

        // ── Inventory Entries ─────────────────────────────────────────────
        $inventoryEntryData = [
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
                    'tenant_id'          => $tid,
                    'stock_item_name'    => $stockItem->name,
                    'hsn_code'           => $stockItem->hsn_code,
                    'unit'               => $stockItem->unit_name,
                    'igst_rate'          => $igstRate,
                    'is_deemed_positive' => true,
                    'actual_qty'         => $qty,
                    'billed_qty'         => $qty,
                    'rate'               => $rate,
                    'discount_percent'   => 0,
                    'amount'             => $amount,
                    'tax_amount'         => $taxAmount,
                    'sales_ledger'       => 'Sales - Domestic',
                ]
            );
        }

        // ── Ledger Entries ────────────────────────────────────────────────
        $ledgerEntryData = [
            [4001, 101, 94400,  true,  true],
            [4001, 301, -80000, false, false],
            [4001, 701, -14400, false, false],

            [4002, 102, 23600,  true,  true],
            [4002, 301, -20000, false, false],
            [4002, 701, -3600,  false, false],

            [4003, 401, 60000,  true,  false],
            [4003, 702, 10800,  true,  false],
            [4003, 201, -70800, false, true],

            [4004, 401, 30000,  true,  false],
            [4004, 702, 5400,   true,  false],
            [4004, 202, -35400, false, true],

            [4005, 501, 94400,  true,  false],
            [4005, 101, -94400, false, true],

            [4006, 201, 70800,  true,  true],
            [4006, 501, -70800, false, false],

            [4007, 102, -11800, false, true],
            [4007, 301, 10000,  true,  false],
            [4007, 701, 1800,   true,  false],

            [4008, 702, 5000,   true,  false],
            [4008, 701, -5000,  false, false],

            [4009, 601, 20000,  true,  false],
            [4009, 501, -20000, false, false],

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
            ['ledger_groups',       'inbound', 'success', false, 8,  0, 0, 0, 0, null],
            ['ledgers',             'inbound', 'success', false, 14, 0, 0, 0, 0, null],
            ['stock_groups',        'inbound', 'success', false, 5,  0, 0, 0, 0, null],
            ['stock_categories',    'inbound', 'success', false, 3,  0, 0, 0, 0, null],
            ['stock_items',         'inbound', 'success', false, 10, 0, 0, 0, 0, null],
            ['vouchers_sales',      'inbound', 'success', false, 2,  0, 0, 0, 0, null],
            ['vouchers_purchase',   'inbound', 'success', false, 2,  0, 0, 0, 0, null],
            ['vouchers_receipt',    'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['vouchers_payment',    'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['vouchers_creditnote', 'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['vouchers_journal',    'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['vouchers_contra',     'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['vouchers_debitnote',  'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['ledger_groups',       'inbound', 'success', false, 0,  2, 6, 0, 0, null],
            ['ledgers',             'inbound', 'success', false, 0,  3, 11,0, 0, null],
            ['stock_items',         'inbound', 'success', false, 0,  4, 6, 0, 0, null],
            ['vouchers_sales',      'inbound', 'success', false, 1,  1, 0, 0, 0, null],
            ['vouchers_purchase',   'inbound', 'success', false, 0,  2, 0, 0, 0, null],
            ['statutory_masters',   'inbound', 'success', false, 8,  0, 0, 0, 0, null],
            ['employee_groups',     'inbound', 'success', false, 5,  0, 0, 0, 0, null],
            ['pay_heads',           'inbound', 'success', false, 10, 0, 0, 0, 0, null],
            ['attendance_types',    'inbound', 'success', false, 7,  0, 0, 0, 0, null],
            ['employees',           'inbound', 'success', false, 5,  0, 0, 0, 0, null],
            ['vouchers_payroll',    'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['vouchers_attendance', 'inbound', 'success', false, 1,  0, 0, 0, 0, null],
            ['manual_trigger',      'inbound', 'success', true,  0,  0, 0, 0, 0, null],
            ['ledgers',             'inbound', 'failed',  false, 0,  0, 0, 3, 0, 'Ledger "XYZ Corp" has duplicate tally_id 14 for this tenant'],
        ];

        foreach ($logData as $i => [$entity, $direction, $status, $manual, $created, $updated, $skipped, $failed, $deleted, $error]) {
            TallySyncLog::create([
                'tenant_id'              => $tid,
                'entity'                 => $entity,
                'direction'              => $direction,
                'status'                 => $status,
                'triggered_manually'     => $manual,
                'records_created'        => $created,
                'records_updated'        => $updated,
                'records_skipped'        => $skipped,
                'records_failed'         => $failed,
                'records_deleted'        => $deleted,
                'error_message'          => $error,
                'started_at'             => now()->subDays(1)->addMinutes($i * 3),
                'completed_at'           => $status === 'failed' ? null : now()->subDays(1)->addMinutes($i * 3 + 1),
            ]);
        }

        // ── Statutory Masters ─────────────────────────────────────────────
        $statutoryData = [
            [5001, 5001, 'GST Registration - Delhi',        'GST', '07AABCT1234A1ZK', '07', 'Regular',       'AABCT1234A', null,         '2017-07-01', ['GSTRate' => 18, 'FilingFrequency' => 'Monthly']],
            [5002, 5002, 'GST Registration - Karnataka',    'GST', '29AABCT1234A2ZK', '29', 'Regular',       'AABCT1234A', null,         '2017-07-01', ['GSTRate' => 18, 'FilingFrequency' => 'Monthly']],
            [5003, 5003, 'TDS - Section 194C (Contractor)', 'TDS', 'TDS-194C',         null, 'TDS Deductor',  null,         'DELT12345A', '2023-04-01', ['Section' => '194C', 'Rate' => 1, 'ThresholdLimit' => 30000]],
            [5004, 5004, 'TDS - Section 194J (Prof. Svc)',  'TDS', 'TDS-194J',         null, 'TDS Deductor',  null,         'DELT12345A', '2023-04-01', ['Section' => '194J', 'Rate' => 10, 'ThresholdLimit' => 30000]],
            [5005, 5005, 'TCS - Section 206C',              'TCS', 'TCS-206C',         null, 'TCS Collector', null,         'DELT12345A', '2023-04-01', ['Section' => '206C', 'Rate' => 0.1]],
            [5006, 5006, 'PF Registration',                 'PF',  'PF/DL/12345/001',  null, 'Employer',      null,         null,         '2020-01-01', ['EmployerRate' => 12, 'EmployeeRate' => 12, 'AdminCharge' => 0.5]],
            [5007, 5007, 'ESI Registration',                'ESI', 'ESI/31/12345/001', null, 'Employer',      null,         null,         '2020-01-01', ['EmployerRate' => 3.25, 'EmployeeRate' => 0.75]],
            [5008, 5008, 'Professional Tax - Delhi',        'PT',  'PT/DL/2024/001',   '07', 'PT Deductor',   null,         null,         '2023-04-01', ['MonthlyLimit' => 15000, 'TaxAmount' => 200]],
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
        $empGroups = [];
        $empGroupData = [
            [6001, 6001, 'Management',         null],
            [6002, 6002, 'Operations',         null],
            [6003, 6003, 'Sales & Marketing',  null],
            [6004, 6004, 'Technology',         null],
            [6005, 6005, 'Finance & Accounts', null],
        ];

        foreach ($empGroupData as [$tallyId, $alterId, $name, $under]) {
            $empGroups[$tallyId] = TallyEmployeeGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'       => $alterId,
                    'name'           => $name,
                    'under'          => $under,
                    'is_active'      => true,
                    'last_synced_at' => now()->subHours(1),
                ]
            );
        }

        // ── Pay Heads ─────────────────────────────────────────────────────
        $payHeadData = [
            // [tallyId, alterId, name, payType, incomeType, parentGroup, calcType, calcPeriod]
            [7001, 7001, 'Basic Salary',         'Earning',                          null, 'Salary Payable', 'On Attendance',    'Monthly'],
            [7002, 7002, 'House Rent Allowance', 'Earning',                          null, 'Salary Payable', 'As Computed Value','Monthly'],
            [7003, 7003, 'Conveyance Allowance', 'Earning',                          null, 'Salary Payable', 'Fixed',            'Monthly'],
            [7004, 7004, 'Special Allowance',    'Earning',                          null, 'Salary Payable', 'As Computed Value','Monthly'],
            [7005, 7005, 'PF - Employee',        'Employees\' Statutory Deductions', null, 'PF Payable',     'As Computed Value','Monthly'],
            [7006, 7006, 'PF - Employer',        'Employer\'s Statutory Contributions',null,'PF Payable',    'As Computed Value','Monthly'],
            [7007, 7007, 'ESI - Employee',       'Employees\' Statutory Deductions', null, 'ESI Payable',    'As Computed Value','Monthly'],
            [7008, 7008, 'ESI - Employer',       'Employer\'s Statutory Contributions',null,'ESI Payable',   'As Computed Value','Monthly'],
            [7009, 7009, 'TDS on Salary',        'Employees\' Statutory Deductions', null, 'TDS Payable',    'As Computed Value','Monthly'],
            [7010, 7010, 'Professional Tax',     'Employees\' Statutory Deductions', null, 'PT Payable',     'Fixed',            'Monthly'],
        ];

        foreach ($payHeadData as [$tallyId, $alterId, $name, $payType, $incomeType, $parentGroup, $calcType, $calcPeriod]) {
            TallyPayHead::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'           => $alterId,
                    'name'               => $name,
                    'pay_type'           => $payType,
                    'income_type'        => $incomeType,
                    'parent_group'       => $parentGroup,
                    'calculation_type'   => $calcType,
                    'calculation_period' => $calcPeriod,
                    'is_active'          => true,
                    'last_synced_at'     => now()->subHours(1),
                ]
            );
        }

        // ── Attendance Types ──────────────────────────────────────────────
        $attendanceData = [
            [8001, 8001, 'Present',        'Attendance',        'Days'],
            [8002, 8002, 'Casual Leave',   'Leave with Pay',    'Days'],
            [8003, 8003, 'Sick Leave',     'Leave with Pay',    'Days'],
            [8004, 8004, 'Unpaid Leave',   'Leave without Pay', 'Days'],
            [8005, 8005, 'Holiday',        'Attendance',        'Days'],
            [8006, 8006, 'Week Off',       'Attendance',        'Days'],
            [8007, 8007, 'Overtime Hours', 'Productivity',      'Hours'],
        ];

        foreach ($attendanceData as [$tallyId, $alterId, $name, $type, $period]) {
            TallyAttendanceType::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'          => $alterId,
                    'name'              => $name,
                    'attendance_type'   => $type,
                    'attendance_period' => $period,
                    'is_active'         => true,
                    'last_synced_at'    => now()->subHours(1),
                ]
            );
        }

        // ── Employees ─────────────────────────────────────────────────────
        $employeeData = [
            // [tallyId, alterId, name, empNo, groupId, designation, doj, dob, gender]
            [9001, 9001, 'Arjun Mehta',   'EMP001', 6001, 'Director',         '2019-06-01', '1980-03-15', 'Male'],
            [9002, 9002, 'Sunita Sharma', 'EMP002', 6004, 'Senior Developer', '2021-01-15', '1992-07-22', 'Female'],
            [9003, 9003, 'Rakesh Gupta',  'EMP003', 6003, 'Sales Manager',    '2020-08-10', '1988-11-05', 'Male'],
            [9004, 9004, 'Pooja Nair',    'EMP004', 6005, 'Accountant',       '2022-03-01', '1995-04-18', 'Female'],
            [9005, 9005, 'Vikram Singh',  'EMP005', 6002, 'Operations Lead',  '2020-11-20', '1990-09-30', 'Male'],
        ];

        foreach ($employeeData as [$tallyId, $alterId, $name, $empNo, $groupId, $designation, $doj, $dob, $gender]) {
            TallyEmployee::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'        => $alterId,
                    'name'            => $name,
                    'employee_number' => $empNo,
                    'parent'          => $empGroups[$groupId]?->name,
                    'designation'     => $designation,
                    'date_of_joining' => $doj,
                    'date_of_birth'   => $dob,
                    'gender'          => $gender,
                    'is_active'       => true,
                    'last_synced_at'  => now()->subHours(1),
                ]
            );
        }

        // ── Payroll Vouchers ──────────────────────────────────────────────
        // Fetch the employees we just seeded so we can link allocations
        $employees = TallyEmployee::withoutGlobalScope('tenant')
            ->where('tenant_id', $tid)
            ->get()
            ->keyBy('name');

        // April 2024 salary run
        $salaryVoucher = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 6001],
            [
                'alter_id'       => 6001,
                'voucher_type'   => 'Payroll',
                'voucher_number' => 'SAL/2024-25/001',
                'voucher_date'   => '2024-04-30',
                'narration'      => 'Salary for the month of April 2024',
                'is_invoice'     => false,
                'is_deleted'     => false,
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]
        );

        // [name, group, payHeadEntries, netPayable]
        $salaryAllocations = [
            ['Arjun Mehta',   'Management',         [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 80000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 32000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 3200],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -9600],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 105400],
            ['Sunita Sharma', 'Technology',          [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 60000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 24000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -7200],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 78200],
            ['Rakesh Gupta',  'Sales & Marketing',  [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 55000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 22000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -6600],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 71800],
            ['Pooja Nair',    'Finance & Accounts', [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 40000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 16000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -4800],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 52600],
            ['Vikram Singh',  'Operations',         [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 50000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 20000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -6000],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 65400],
        ];

        foreach ($salaryAllocations as [$empName, $empGroup, $payHeadEntries, $netPayable]) {
            TallyVoucherEmployeeAllocation::firstOrCreate(
                ['tally_voucher_id' => $salaryVoucher->id, 'employee_name' => $empName],
                [
                    'tenant_id'         => $tid,
                    'tally_employee_id' => $employees[$empName]?->id,
                    'employee_group'    => $empGroup,
                    'entries'           => $payHeadEntries,
                    'net_payable'       => $netPayable,
                ]
            );
        }

        // April 2024 attendance run
        $attendanceVoucher = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 6002],
            [
                'alter_id'       => 6002,
                'voucher_type'   => 'Attendance',
                'voucher_number' => 'ATT/2024-25/001',
                'voucher_date'   => '2024-04-30',
                'narration'      => 'Attendance for the month of April 2024',
                'is_invoice'     => false,
                'is_deleted'     => false,
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]
        );

        // [name, group, attendanceEntries]
        $attendanceAllocations = [
            ['Arjun Mehta',   'Management',         [
                ['AttendanceType' => 'Present',      'Units' => 25],
                ['AttendanceType' => 'Casual Leave', 'Units' => 1],
                ['AttendanceType' => 'Week Off',     'Units' => 4],
            ]],
            ['Sunita Sharma', 'Technology',          [
                ['AttendanceType' => 'Present',    'Units' => 23],
                ['AttendanceType' => 'Sick Leave', 'Units' => 2],
                ['AttendanceType' => 'Week Off',   'Units' => 5],
            ]],
            ['Rakesh Gupta',  'Sales & Marketing',  [
                ['AttendanceType' => 'Present',       'Units' => 24],
                ['AttendanceType' => 'Unpaid Leave',  'Units' => 1],
                ['AttendanceType' => 'Week Off',      'Units' => 5],
            ]],
            ['Pooja Nair',    'Finance & Accounts', [
                ['AttendanceType' => 'Present',  'Units' => 26],
                ['AttendanceType' => 'Week Off', 'Units' => 4],
            ]],
            ['Vikram Singh',  'Operations',         [
                ['AttendanceType' => 'Present',      'Units' => 22],
                ['AttendanceType' => 'Casual Leave', 'Units' => 2],
                ['AttendanceType' => 'Week Off',     'Units' => 6],
            ]],
        ];

        foreach ($attendanceAllocations as [$empName, $empGroup, $attendanceEntries]) {
            TallyVoucherEmployeeAllocation::firstOrCreate(
                ['tally_voucher_id' => $attendanceVoucher->id, 'employee_name' => $empName],
                [
                    'tenant_id'         => $tid,
                    'tally_employee_id' => $employees[$empName]?->id,
                    'employee_group'    => $empGroup,
                    'entries'           => $attendanceEntries,
                    'net_payable'       => null,
                ]
            );
        }

        // ── ISP / Lease Line dataset ──────────────────────────────────────
        $bluestarLedger = TallyLedger::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 105],
            [
                'alter_id'             => 205,
                'ledger_name'          => 'BlueStar Technologies',
                'group_name'           => 'Sundry Debtors',
                'ledger_category'      => 'customer',
                'gstin_number'         => '27AABCT1234A1Z5',
                'gst_type'             => 'Regular',
                'is_bill_wise_on'      => true,
                'inventory_affected'   => false,
                'mailing_name'         => 'BlueStar Technologies Pvt Ltd',
                'mobile_number'        => '9876543210',
                'contact_person'       => 'Rajesh Kumar',
                'contact_person_email' => 'rajesh@bluestar.in',
                'contact_person_mobile'=> '9876543210',
                'addresses'            => ['123 MG Road', 'Bangalore - 560001'],
                'state_name'           => 'Karnataka',
                'country_name'         => 'India',
                'pin_code'             => '560001',
                'credit_period'        => 30,
                'credit_limit'         => 500000,
                'opening_balance'      => 125000,
                'opening_balance_type' => 'Dr',
                'is_active'            => true,
                'last_synced_at'       => now()->subHours(1),
            ]
        );
        $ledgers[105] = $bluestarLedger;

        $bluestarClient = Client::firstOrCreate(
            ['tenant_id' => $tid, 'tally_ledger_id' => $bluestarLedger->id],
            [
                'name'      => 'BlueStar Technologies Pvt Ltd',
                'email'     => 'rajesh@bluestar.in',
                'phone'     => '9876543210',
                'company'   => 'BlueStar Technologies',
                'tax_id'    => '27AABCT1234A1Z5',
                'tenant_id' => $tid,
            ]
        );
        $bluestarLedger->update(['mapped_client_id' => $bluestarClient->id]);

        $salesLeaseLedger = TallyLedger::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 305],
            [
                'alter_id'           => 305,
                'ledger_name'        => 'Sales - Lease Line',
                'group_name'         => 'Sales Accounts',
                'ledger_category'    => 'income',
                'is_bill_wise_on'    => false,
                'inventory_affected' => false,
                'is_active'          => true,
                'last_synced_at'     => now()->subHours(1),
            ]
        );
        $ledgers[305] = $salesLeaseLedger;

        TallyStockGroup::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 10],
            [
                'alter_id'       => 55,
                'name'           => 'Network Equipment',
                'parent'         => 'Primary',
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]
        );

        TallyStockCategory::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 5],
            [
                'alter_id'       => 11,
                'name'           => 'Lease Line Services',
                'parent'         => 'Primary',
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]
        );

        $leaseLineItem = TallyStockItem::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 201],
            [
                'alter_id'          => 88,
                'name'              => '30Mbps Lease Line',
                'description'       => 'Dedicated internet lease line 30Mbps',
                'stock_group_id'    => 10,
                'stock_group_name'  => 'Network Equipment',
                'stock_category_id' => 5,
                'category_name'     => 'Lease Line Services',
                'unit_id'           => 3,
                'unit_name'         => 'Nos',
                'denominator'       => 1,
                'is_gst_applicable' => true,
                'taxability'        => 'Taxable',
                'calculation_type'  => 'On Value',
                'igst_rate'         => 18,
                'sgst_rate'         => 9,
                'cgst_rate'         => 9,
                'cess_rate'         => 0,
                'hsn_code'          => '998422',
                'mrp_rate'          => 15000,
                'opening_balance'   => 0,
                'opening_rate'      => 0,
                'opening_value'     => 0,
                'closing_balance'   => 0,
                'closing_rate'      => 0,
                'closing_value'     => 0,
                'is_active'         => true,
                'last_synced_at'    => now()->subHours(1),
            ]
        );
        $stockItems[201] = $leaseLineItem;

        $leaseLineProduct = Product::firstOrCreate(
            ['tenant_id' => $tid, 'tally_stock_item_id' => $leaseLineItem->id],
            [
                'name'        => '30Mbps Lease Line',
                'description' => 'Dedicated internet lease line 30Mbps',
                'unit'        => 'Nos',
                'unit_price'  => 15000,
                'tax_rate'    => 18,
                'is_active'   => true,
                'tenant_id'   => $tid,
            ]
        );
        $leaseLineItem->update(['mapped_product_id' => $leaseLineProduct->id]);

        $igstLedger = $ledgers[701];
        foreach ([
            [5001, 300, '2024-25/INV/001', '2024-04-01', 'Monthly lease line charges for April 2024'],
            [5002, 301, '2024-25/INV/002', '2024-05-01', 'Monthly lease line charges for May 2024'],
        ] as [$tallyId, $alterId, $number, $date, $narration]) {
            $voucher = TallyVoucher::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'              => $alterId,
                    'voucher_type'          => 'Sales',
                    'voucher_number'        => $number,
                    'voucher_date'          => $date,
                    'party_name'            => 'BlueStar Technologies',
                    'party_tally_ledger_id' => $bluestarLedger->id,
                    'voucher_total'         => 17700,
                    'is_invoice'            => true,
                    'is_deleted'            => false,
                    'place_of_supply'       => 'Karnataka',
                    'buyer_name'            => 'BlueStar Technologies Pvt Ltd',
                    'buyer_gstin'           => '27AABCT1234A1Z5',
                    'buyer_state'           => 'Karnataka',
                    'buyer_address'         => '123 MG Road, Bangalore',
                    'buyer_email'           => 'rajesh@bluestar.in',
                    'buyer_mobile'          => '9876543210',
                    'narration'             => $narration,
                    'is_active'             => true,
                    'last_synced_at'        => now()->subMinutes(30),
                ]
            );

            TallyVoucherInventoryEntry::firstOrCreate(
                ['tally_voucher_id' => $voucher->id, 'tally_stock_item_id' => $leaseLineItem->id],
                [
                    'tenant_id'          => $tid,
                    'stock_item_name'    => '30Mbps Lease Line',
                    'hsn_code'           => '998422',
                    'unit'               => 'Nos',
                    'igst_rate'          => 18,
                    'is_deemed_positive' => true,
                    'actual_qty'         => 1,
                    'billed_qty'         => 1,
                    'rate'               => 15000,
                    'discount_percent'   => 0,
                    'amount'             => 15000,
                    'tax_amount'         => 2700,
                    'sales_ledger'       => 'Sales - Lease Line',
                ]
            );

            foreach ([
                [$bluestarLedger,   'BlueStar Technologies', 'Sundry Debtors', 17700,   true,  true],
                [$salesLeaseLedger, 'Sales - Lease Line',    'Sales Accounts', -15000,  false, false],
                [$igstLedger,       'IGST @18%',             'Duties & Taxes', -2700,   false, false],
            ] as [$ledger, $name, $group, $amount, $deemed, $isParty]) {
                TallyVoucherLedgerEntry::firstOrCreate(
                    ['tally_voucher_id' => $voucher->id, 'tally_ledger_id' => $ledger->id],
                    [
                        'tenant_id'          => $tid,
                        'ledger_name'        => $name,
                        'ledger_group'       => $group,
                        'ledger_amount'      => $amount,
                        'is_deemed_positive' => $deemed,
                        'is_party_ledger'    => $isParty,
                    ]
                );
            }

            $invoice = Invoice::firstOrCreate(
                ['tally_voucher_id' => $voucher->id, 'tenant_id' => $tid],
                [
                    'tenant_id'      => $tid,
                    'client_id'      => $bluestarClient->id,
                    'invoice_number' => $number,
                    'issue_date'     => $date,
                    'due_date'       => $date,
                    'status'         => 'sent',
                    'subtotal'       => 15000,
                    'tax_amount'     => 2700,
                    'total'          => 17700,
                    'currency'       => 'INR',
                    'notes'          => $narration,
                    'amount_paid'    => 0,
                    'amount_due'     => 17700,
                ]
            );
            $voucher->update(['mapped_invoice_id' => $invoice->id]);

            InvoiceItem::firstOrCreate(
                ['invoice_id' => $invoice->id, 'product_id' => $leaseLineProduct->id],
                [
                    'description' => '30Mbps Lease Line',
                    'unit'        => 'Nos',
                    'quantity'    => 1,
                    'unit_price'  => 15000,
                    'tax_rate'    => 18,
                    'tax_amount'  => 2700,
                    'total'       => 17700,
                ]
            );
        }

        $this->command->info('TallySeeder: seeded connection, company, 8 ledger groups, 14 ledgers, 5 stock groups, 3 categories, 2 godowns, 10 stock items, 10 vouchers, 1 payroll voucher (5 employee allocations), 1 attendance voucher (5 employee allocations), 8 statutory masters, 5 employee groups, 10 pay heads, 7 attendance types, 5 employees, reports, sync logs for Tili.');
    }
}
