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
use App\Services\Tally\TallyInboundSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TallySeeder extends Seeder
{
    public function run(): void
    {
        // Suppress outbound observer so seeded records don't get queued for sync.
        TallyInboundSync::$syncing = true;
        $tenant = Tenant::where('name', 'Tili')->firstOrFail();
        $tid    = $tenant->id;

        // ── Connection ────────────────────────────────────────────────────
        $connection = TallyConnection::firstOrCreate(['tenant_id' => $tid], [
            'company_id'                 => 'TILI2025',
            'is_active'                  => true,
            'inbound_token'              => Str::random(48),
            'inbound_token_last_used_at' => now()->subHours(2),
            'last_synced_at'             => now()->subMinutes(30),
        ]);

        // ── Company ───────────────────────────────────────────────────────
        TallyCompany::firstOrCreate(
            ['tally_connection_id' => $connection->id],
            [
                'company_guid'   => 'TILI-GUID-2025-001',
                'company_name'   => 'Tili Electronics Pvt Ltd',
                'licence_type'   => 'Silver',
                'licence_number' => 'TILI-LIC-2025',
            ]
        );

        // ── Ledger Groups (28 standard Tally Prime groups) ────────────────
        TallyLedgerGroup::where('tenant_id', $tid)->delete();

        $groups = [];
        foreach ([
            [ 1,  1, 'Branch / Divisions',      null, null],
            [ 2,  2, 'Capital Account',          null, null],
            [ 3,  3, 'Current Assets',           null, null],
            [ 4,  4, 'Current Liabilities',      null, null],
            [ 5,  5, 'Direct Expenses',          null, null],
            [ 6,  6, 'Direct Incomes',           null, null],
            [ 7,  7, 'Fixed Assets',             null, null],
            [ 8,  8, 'Indirect Expenses',        null, null],
            [ 9,  9, 'Indirect Incomes',         null, null],
            [10, 10, 'Investments',              null, null],
            [11, 11, 'Loans (Liability)',         null, null],
            [12, 12, 'Misc. Expenses (ASSET)',   null, null],
            [13, 13, 'Purchase Accounts',        null, null],
            [14, 14, 'Sales Accounts',           null, null],
            [15, 15, 'Suspense A/c',             null, null],
            [16, 16, 'Reserves & Surplus',       2,  'Capital Account'],
            [17, 17, 'Bank Accounts',            3,  'Current Assets'],
            [18, 18, 'Cash-in-Hand',             3,  'Current Assets'],
            [19, 19, 'Deposits (Asset)',          3,  'Current Assets'],
            [20, 20, 'Loans & Advances (Asset)', 3,  'Current Assets'],
            [21, 21, 'Stock-in-Hand',            3,  'Current Assets'],
            [22, 22, 'Sundry Debtors',           3,  'Current Assets'],
            [23, 23, 'Duties & Taxes',           4,  'Current Liabilities'],
            [24, 24, 'Provisions',               4,  'Current Liabilities'],
            [25, 25, 'Sundry Creditors',         4,  'Current Liabilities'],
            [26, 26, 'Bank OD A/c',              11, 'Loans (Liability)'],
            [27, 27, 'Secured Loans',            11, 'Loans (Liability)'],
            [28, 28, 'Unsecured Loans',          11, 'Loans (Liability)'],
        ] as [$tallyId, $alterId, $name, $underId, $underName]) {
            $groups[$name] = TallyLedgerGroup::create([
                'tenant_id'      => $tid,
                'tally_id'       => $tallyId,
                'alter_id'       => $alterId,
                'name'           => $name,
                'under_id'       => $underId,
                'under_name'     => $underName,
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]);
        }

        // ── Ledgers ───────────────────────────────────────────────────────
        // Company is Delhi-based (07). Intra-state = CGST+SGST. Inter-state = IGST.
        //
        // [id, name, group, gstin, gstType, mailingName, mobile, email, pin, state, creditDays, creditLimit, openBal, openType, billWise]
        $ledgerRows = [
            // Debtors
            [101, 'Rahul Enterprises',   'Sundry Debtors',   '07AABCP1234D1ZK', 'Regular', 'Rahul Enterprises',        '9876543210', 'rahul@rahulenterprises.com',   '110001', 'Delhi',       30,  500000,       0, 'Dr', true],
            [102, 'Priya Trading Co.',   'Sundry Debtors',   '29AACCQ5678E2ZL', 'Regular', 'Priya Trading Co.',        '9123456780', 'accounts@priyatrading.com',    '560001', 'Karnataka',   45,  300000,       0, 'Dr', true],
            [103, 'Suresh & Sons',        'Sundry Debtors',   '27AAFPS9012H1ZN', 'Regular', 'Suresh & Sons',            '9823456789', 'suresh@sureshandsons.com',     '400001', 'Maharashtra', 30,  150000,       0, 'Dr', true],
            [104, 'Meenakshi Retail',    'Sundry Debtors',   '33AABPM5678K1ZL', 'Regular', 'Meenakshi Retail Pvt Ltd', '9445566778', 'accounts@meenakshiretail.in',  '600001', 'Tamil Nadu',  30,  100000,       0, 'Dr', true],
            // Creditors
            [201, 'Kapoor Suppliers',    'Sundry Creditors', '07AAFPK9012F3ZM', 'Regular', 'Kapoor Suppliers Pvt Ltd', '9871234560', 'kapoor@kapoorsuppliers.com',   '110002', 'Delhi',       60,  400000,       0, 'Cr', true],
            [202, 'National Goods Ltd',  'Sundry Creditors', '32AABPN3456G4ZN', 'Regular', 'National Goods Ltd',       '9988776655', 'national@nationalgoods.in',    '682001', 'Kerala',      30,  250000,       0, 'Cr', true],
            // Sales / Purchase
            [301, 'Sales - Electronics', 'Sales Accounts',   null, null, null, null, null, null, null,  0,       0,  0, 'Cr', false],
            [302, 'Sales - Accessories', 'Sales Accounts',   null, null, null, null, null, null, null,  0,       0,  0, 'Cr', false],
            [401, 'Purchase - Electronics', 'Purchase Accounts', null, null, null, null, null, null, null, 0,    0,  0, 'Dr', false],
            // Bank / Cash
            [501, 'HDFC Bank A/c',       'Bank Accounts',    null, null, null, null, null, null, null,  0,       0,  800000, 'Dr', false],
            [601, 'Cash',                'Cash-in-Hand',     null, null, null, null, null, null, null,  0,       0,   75000, 'Dr', false],
            // GST — inter-state
            [701, 'Output IGST',         'Duties & Taxes',   null, null, null, null, null, null, null,  0,       0,  0, 'Cr', false],
            [702, 'Input IGST',          'Duties & Taxes',   null, null, null, null, null, null, null,  0,       0,  0, 'Dr', false],
            // GST — intra-state (Delhi)
            [703, 'Output CGST',         'Duties & Taxes',   null, null, null, null, null, null, null,  0,       0,  0, 'Cr', false],
            [704, 'Output SGST',         'Duties & Taxes',   null, null, null, null, null, null, null,  0,       0,  0, 'Cr', false],
            [705, 'Input CGST',          'Duties & Taxes',   null, null, null, null, null, null, null,  0,       0,  0, 'Dr', false],
            [706, 'Input SGST',          'Duties & Taxes',   null, null, null, null, null, null, null,  0,       0,  0, 'Dr', false],
        ];

        $ledgers = [];
        $clients = Client::where('tenant_id', $tid)->take(4)->get()->keyBy('name');
        $vendors = Vendor::where('tenant_id', $tid)->take(2)->get()->keyBy('name');

        foreach ($ledgerRows as [$id, $name, $group, $gstin, $gstType, $mailing, $mobile, $email, $pin, $state, $creditDays, $creditLimit, $openBal, $openType, $billWise]) {
            $mappedClientId = ($group === 'Sundry Debtors'  && isset($clients[$name])) ? $clients[$name]->id : null;
            $mappedVendorId = ($group === 'Sundry Creditors' && $vendors->first())     ? $vendors->first()->id : null;
            $category = match(true) {
                str_contains(strtolower($group), 'debtor')   => 'customer',
                str_contains(strtolower($group), 'creditor') => 'vendor',
                default                                      => null,
            };
            $appropriateFor = null;
            if ($group === 'Duties & Taxes') {
                if      (stripos($name, 'IGST') !== false) $appropriateFor = 'IGST';
                elseif  (stripos($name, 'CGST') !== false) $appropriateFor = 'CGST';
                elseif  (stripos($name, 'SGST') !== false) $appropriateFor = 'SGST';
                elseif  (stripos($name, 'CESS') !== false) $appropriateFor = 'Cess';
            }

            $ledgers[$id] = TallyLedger::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                [
                    'alter_id'              => $id,
                    'ledger_name'           => $name,
                    'group_name'            => $group,
                    'ledger_category'       => $category,
                    'gstin_number'          => $gstin,
                    'gst_registration_type' => $gstType,
                    'appropriate_for'       => $appropriateFor,
                    'mailing_name'          => $mailing ?? $name,
                    'contact_person'        => $mailing,
                    'contact_person_mobile' => $mobile,
                    'contact_person_email'  => $email,
                    'mobile_number'         => $mobile,
                    'pin_code'              => $pin,
                    'state_name'            => $state,
                    'country_name'          => $state ? 'India' : null,
                    'credit_period'         => $creditDays,
                    'credit_limit'          => $creditLimit,
                    'opening_balance'       => $openBal,
                    'opening_balance_type'  => $openType,
                    'is_bill_wise_on'       => $billWise,
                    'inventory_affected'    => false,
                    'is_active'             => true,
                    'last_synced_at'        => now()->subHours(1),
                    'mapped_client_id'      => $mappedClientId,
                    'mapped_vendor_id'      => $mappedVendorId,
                    'addresses'             => $pin ? [['Address' => '123 Main Road, ' . ($state ?? '') . ' - ' . $pin]] : null,
                ]
            );
        }

        // ── Stock Groups ──────────────────────────────────────────────────
        $stockGroups = [];
        foreach ([
            [1001, 'Electronics',      null],
            [1002, 'Furniture',        null],
            [1003, 'Office Supplies',  null],
            [1004, 'Laptops',          'Electronics'],
            [1005, 'Mobile Phones',    'Electronics'],
        ] as [$id, $name, $parent]) {
            $stockGroups[$id] = TallyStockGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                ['alter_id' => $id, 'name' => $name, 'parent' => $parent, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
            );
        }

        // ── Stock Categories ──────────────────────────────────────────────
        $stockCategories = [];
        foreach ([
            [2001, 'Category A - Premium'],
            [2002, 'Category B - Standard'],
            [2003, 'Category C - Budget'],
        ] as [$id, $name]) {
            $stockCategories[$id] = TallyStockCategory::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                ['alter_id' => $id, 'name' => $name, 'parent' => null, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
            );
        }

        // ── Godowns ───────────────────────────────────────────────────────
        foreach ([
            [1, 'Main Godown',      null],
            [2, 'Secondary Godown', 'Main Godown'],
        ] as [$id, $name, $under]) {
            TallyGodown::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                ['alter_id' => $id, 'guid' => 'GUID-GDN-00' . $id, 'name' => $name, 'under' => $under, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
            );
        }

        // ── Stock Items ───────────────────────────────────────────────────
        // [id, name, stockGroupId, stockGroupName, stockCatId, catName, unit, igst, hsn, mrp, openQty, openRate]
        $products     = Product::where('tenant_id', $tid)->take(5)->get()->values();
        $stockItems   = [];
        foreach ([
            [3001, 'Dell Laptop 15"',    1004, 'Laptops',         2001, 'Category A - Premium',  'Nos', 18, '84713019', 80000, 10, 70000],
            [3002, 'HP Laptop 14"',       1004, 'Laptops',         2002, 'Category B - Standard', 'Nos', 18, '84713019', 65000,  5, 55000],
            [3003, 'Samsung Galaxy S24',  1005, 'Mobile Phones',   2001, 'Category A - Premium',  'Nos', 18, '85171200', 85000, 15, 75000],
            [3004, 'iPhone 15',           1005, 'Mobile Phones',   2001, 'Category A - Premium',  'Nos', 18, '85171200', 95000,  8, 88000],
            [3005, 'Office Chair',        1002, 'Furniture',       2002, 'Category B - Standard', 'Nos', 18, '94017100', 15000, 20, 11000],
            [3006, 'Standing Desk',       1002, 'Furniture',       2002, 'Category B - Standard', 'Nos', 18, '94031090', 28000, 10, 22000],
            [3007, 'A4 Paper (Ream)',     1003, 'Office Supplies',  2003, 'Category C - Budget',  'Pkt', 12, '48025590',   550, 100,   450],
            [3008, 'Printer Cartridge',   1003, 'Office Supplies',  2003, 'Category C - Budget',  'Nos', 18, '84439910',  1800,  50,  1200],
            [3009, 'USB-C Hub',           1001, 'Electronics',     2002, 'Category B - Standard', 'Nos', 18, '85176900',  4000,  30,  3000],
            [3010, 'Wireless Keyboard',   1001, 'Electronics',     2002, 'Category B - Standard', 'Nos', 18, '84716060',  3200,  25,  2500],
        ] as $i => [$id, $name, $sgId, $sgName, $scId, $scName, $unit, $igst, $hsn, $mrp, $openQty, $openRate]) {
            $stockItems[$id] = TallyStockItem::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                [
                    'alter_id'          => $id,
                    'name'              => $name,
                    'stock_group_id'    => $sgId,
                    'stock_group_name'  => $sgName,
                    'stock_category_id' => $scId,
                    'category_name'     => $scName,
                    'unit_name'         => $unit,
                    'is_gst_applicable' => true,
                    'taxability'        => 'Taxable',
                    'calculation_type'  => 'On Value',
                    'igst_rate'         => $igst,
                    'cgst_rate'         => $igst / 2,
                    'sgst_rate'         => $igst / 2,
                    'cess_rate'         => 0,
                    'hsn_code'          => $hsn,
                    'mrp_rate'          => $mrp,
                    'opening_balance'   => $openQty,
                    'opening_rate'      => $openRate,
                    'opening_value'     => $openQty * $openRate,
                    'closing_balance'   => max(0, $openQty - rand(1, 4)),
                    'closing_rate'      => $openRate,
                    'closing_value'     => max(0, $openQty - rand(1, 4)) * $openRate,
                    'is_active'         => true,
                    'last_synced_at'    => now()->subHours(1),
                    'mapped_product_id' => $products->get($i)?->id,
                ]
            );
        }

        // ── Vouchers ──────────────────────────────────────────────────────
        //
        // Business: Tili Electronics Pvt Ltd, Delhi (07)
        // FY 2025-26
        //
        // Outstanding bills at seed time:
        //   Priya Trading  SV/25-26/002: ₹88,800 partial (receipt ₹1,00,000 + CN ₹94,400 net against ₹2,83,200 — see below)
        //                  Actually: receipt ₹50,000 + CN ₹94,400 = ₹1,44,400 settled of ₹1,88,800 → ₹44,400 outstanding
        //                  SV/25-26/004: ₹1,06,200 fully open
        //   Meenakshi      SV/25-26/005: ₹33,040 fully open
        //   National Goods PV/25-26/002: ₹1,47,500 (₹1,53,400 – ₹5,900 debit note)

        $v = []; // voucher map by tally_id

        // ── SV/25-26/001 — Rahul Enterprises, Delhi (intra-state: CGST+SGST) ──
        // 2 Dell Laptops × ₹75,000 = ₹1,50,000 + CGST 9% ₹13,500 + SGST 9% ₹13,500 = ₹1,77,000
        $v[4001] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4001],
            [
                'alter_id'              => 4001,
                'voucher_type'          => 'Sales',
                'voucher_base_type'     => 'Sales',
                'voucher_number'        => 'SV/25-26/001',
                'voucher_date'          => '2025-04-02',
                'party_name'            => 'Rahul Enterprises',
                'party_tally_ledger_id' => $ledgers[101]->id,
                'voucher_total'         => 177000,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'place_of_supply'       => 'Delhi',
                'buyer_name'            => 'Rahul Enterprises',
                'buyer_gstin'           => '07AABCP1234D1ZK',
                'buyer_gst_registration_type' => 'Regular',
                'buyer_state'           => 'Delhi',
                'buyer_country'         => 'India',
                'buyer_address'         => '12 Connaught Place, New Delhi - 110001',
                'buyer_mobile'          => '9876543210',
                'buyer_email'           => 'rahul@rahulenterprises.com',
                'narration'             => 'Sale of Dell Laptops to Rahul Enterprises',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4001]->id, 'tally_stock_item_id' => $stockItems[3001]->id],
            [
                'tenant_id'               => $tid,
                'stock_item_name'         => 'Dell Laptop 15"',
                'hsn_code'                => '84713019',
                'unit'                    => 'Nos',
                'igst_rate'               => 0,
                'is_deemed_positive'      => false,
                'actual_qty'              => 2,
                'billed_qty'              => 2,
                'rate'                    => 75000,
                'discount_percent'        => 0,
                'amount'                  => 150000,
                'tax_amount'              => 27000,
                'sales_ledger'            => 'Sales - Electronics',
                'godown_name'             => 'Main Godown',
                'accounting_allocations'  => [
                    ['LedgerName' => 'Sales - Electronics', 'LedgerGroup' => 'Sales Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 0, 'Amount' => 150000],
                ],
            ]
        );
        // Ledger entries: Rahul Dr 1,77,000 | Sales Cr 1,50,000 | Output CGST Cr 13,500 | Output SGST Cr 13,500
        $this->le($tid, $v[4001], $ledgers[101],  177000, true,  true,  [['AgstType' => 'New Ref', 'Reference' => 'SV/25-26/001', 'CreditPeriod' => '30 Days', 'Amount' => 177000]]);
        $this->le($tid, $v[4001], $ledgers[301], 150000, false, false, []);
        $this->le($tid, $v[4001], $ledgers[703], 13500, false, false, []);
        $this->le($tid, $v[4001], $ledgers[704], 13500, false, false, []);

        // ── SV/25-26/002 — Priya Trading Co., Karnataka (inter-state: IGST) ──
        // 2 Samsung Galaxy S24 × ₹80,000 = ₹1,60,000 + IGST 18% ₹28,800 = ₹1,88,800
        $v[4002] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4002],
            [
                'alter_id'              => 4002,
                'voucher_type'          => 'Sales',
                'voucher_base_type'     => 'Sales',
                'voucher_number'        => 'SV/25-26/002',
                'voucher_date'          => '2025-04-05',
                'party_name'            => 'Priya Trading Co.',
                'party_tally_ledger_id' => $ledgers[102]->id,
                'voucher_total'         => 188800,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'place_of_supply'       => 'Karnataka',
                'buyer_name'            => 'Priya Trading Co.',
                'buyer_gstin'           => '29AACCQ5678E2ZL',
                'buyer_gst_registration_type' => 'Regular',
                'buyer_state'           => 'Karnataka',
                'buyer_country'         => 'India',
                'buyer_address'         => '45 MG Road, Bangalore - 560001',
                'buyer_mobile'          => '9123456780',
                'buyer_email'           => 'accounts@priyatrading.com',
                'narration'             => 'Sale of Samsung Galaxy S24 to Priya Trading Co.',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4002]->id, 'tally_stock_item_id' => $stockItems[3003]->id],
            [
                'tenant_id'              => $tid,
                'stock_item_name'        => 'Samsung Galaxy S24',
                'hsn_code'               => '85171200',
                'unit'                   => 'Nos',
                'igst_rate'              => 18,
                'is_deemed_positive'     => false,
                'actual_qty'             => 2,
                'billed_qty'             => 2,
                'rate'                   => 80000,
                'discount_percent'       => 0,
                'amount'                 => 160000,
                'tax_amount'             => 28800,
                'sales_ledger'           => 'Sales - Electronics',
                'godown_name'            => 'Main Godown',
                'accounting_allocations' => [
                    ['LedgerName' => 'Sales - Electronics', 'LedgerGroup' => 'Sales Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 18, 'Amount' => 160000],
                ],
            ]
        );
        $this->le($tid, $v[4002], $ledgers[102],  188800, true,  true,  [['AgstType' => 'New Ref', 'Reference' => 'SV/25-26/002', 'CreditPeriod' => '45 Days', 'Amount' => 188800]]);
        $this->le($tid, $v[4002], $ledgers[301], 160000, false, false, []);
        $this->le($tid, $v[4002], $ledgers[701], 28800, false, false, []);

        // ── SV/25-26/003 — Suresh & Sons, Maharashtra (inter-state: IGST) ──
        // 5 Office Chairs × ₹12,000 = ₹60,000 + IGST 18% ₹10,800 = ₹70,800
        $v[4003] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4003],
            [
                'alter_id'              => 4003,
                'voucher_type'          => 'Sales',
                'voucher_base_type'     => 'Sales',
                'voucher_number'        => 'SV/25-26/003',
                'voucher_date'          => '2025-04-10',
                'party_name'            => 'Suresh & Sons',
                'party_tally_ledger_id' => $ledgers[103]->id,
                'voucher_total'         => 70800,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'place_of_supply'       => 'Maharashtra',
                'buyer_name'            => 'Suresh & Sons',
                'buyer_gstin'           => '27AAFPS9012H1ZN',
                'buyer_gst_registration_type' => 'Regular',
                'buyer_state'           => 'Maharashtra',
                'buyer_country'         => 'India',
                'buyer_address'         => '8 Nariman Point, Mumbai - 400001',
                'buyer_mobile'          => '9823456789',
                'buyer_email'           => 'suresh@sureshandsons.com',
                'narration'             => 'Sale of Office Chairs to Suresh & Sons',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4003]->id, 'tally_stock_item_id' => $stockItems[3005]->id],
            [
                'tenant_id'              => $tid,
                'stock_item_name'        => 'Office Chair',
                'hsn_code'               => '94017100',
                'unit'                   => 'Nos',
                'igst_rate'              => 18,
                'is_deemed_positive'     => false,
                'actual_qty'             => 5,
                'billed_qty'             => 5,
                'rate'                   => 12000,
                'discount_percent'       => 0,
                'amount'                 => 60000,
                'tax_amount'             => 10800,
                'sales_ledger'           => 'Sales - Electronics',
                'godown_name'            => 'Main Godown',
                'accounting_allocations' => [
                    ['LedgerName' => 'Sales - Electronics', 'LedgerGroup' => 'Sales Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 18, 'Amount' => 60000],
                ],
            ]
        );
        $this->le($tid, $v[4003], $ledgers[103],  70800, true,  true,  [['AgstType' => 'New Ref', 'Reference' => 'SV/25-26/003', 'CreditPeriod' => '30 Days', 'Amount' => 70800]]);
        $this->le($tid, $v[4003], $ledgers[301], 60000, false, false, []);
        $this->le($tid, $v[4003], $ledgers[701], 10800, false, false, []);

        // ── PV/25-26/001 — Kapoor Suppliers, Delhi (intra-state: CGST+SGST) ──
        // 3 Dell Laptops × ₹60,000 = ₹1,80,000 + CGST 9% ₹16,200 + SGST 9% ₹16,200 = ₹2,12,400
        $v[4004] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4004],
            [
                'alter_id'              => 4004,
                'voucher_type'          => 'Purchase',
                'voucher_base_type'     => 'Purchase',
                'voucher_number'        => 'PV/25-26/001',
                'voucher_date'          => '2025-04-03',
                'party_name'            => 'Kapoor Suppliers',
                'party_tally_ledger_id' => $ledgers[201]->id,
                'voucher_total'         => 212400,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'narration'             => 'Purchase of Dell Laptops from Kapoor Suppliers',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4004]->id, 'tally_stock_item_id' => $stockItems[3001]->id],
            [
                'tenant_id'              => $tid,
                'stock_item_name'        => 'Dell Laptop 15"',
                'hsn_code'               => '84713019',
                'unit'                   => 'Nos',
                'igst_rate'              => 0,
                'is_deemed_positive'     => true,
                'actual_qty'             => 3,
                'billed_qty'             => 3,
                'rate'                   => 60000,
                'discount_percent'       => 0,
                'amount'                 => 180000,
                'tax_amount'             => 32400,
                'sales_ledger'           => 'Purchase - Electronics',
                'godown_name'            => 'Main Godown',
                'accounting_allocations' => [
                    ['LedgerName' => 'Purchase - Electronics', 'LedgerGroup' => 'Purchase Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 0, 'Amount' => 180000],
                ],
            ]
        );
        $this->le($tid, $v[4004], $ledgers[401],  180000, true,  false, []);
        $this->le($tid, $v[4004], $ledgers[705],   16200, true,  false, []);
        $this->le($tid, $v[4004], $ledgers[706],   16200, true,  false, []);
        $this->le($tid, $v[4004], $ledgers[201], 212400, false, true,  [['AgstType' => 'New Ref', 'Reference' => 'PV/25-26/001', 'CreditPeriod' => '60 Days', 'Amount' => 212400]]);

        // ── PV/25-26/002 — National Goods Ltd, Kerala (inter-state: IGST) ──
        // 2 Samsung Galaxy S24 × ₹65,000 = ₹1,30,000 + IGST 18% ₹23,400 = ₹1,53,400
        $v[4005] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4005],
            [
                'alter_id'              => 4005,
                'voucher_type'          => 'Purchase',
                'voucher_base_type'     => 'Purchase',
                'voucher_number'        => 'PV/25-26/002',
                'voucher_date'          => '2025-04-12',
                'party_name'            => 'National Goods Ltd',
                'party_tally_ledger_id' => $ledgers[202]->id,
                'voucher_total'         => 153400,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'narration'             => 'Purchase of Samsung Galaxy S24 from National Goods Ltd',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4005]->id, 'tally_stock_item_id' => $stockItems[3003]->id],
            [
                'tenant_id'              => $tid,
                'stock_item_name'        => 'Samsung Galaxy S24',
                'hsn_code'               => '85171200',
                'unit'                   => 'Nos',
                'igst_rate'              => 18,
                'is_deemed_positive'     => true,
                'actual_qty'             => 2,
                'billed_qty'             => 2,
                'rate'                   => 65000,
                'discount_percent'       => 0,
                'amount'                 => 130000,
                'tax_amount'             => 23400,
                'sales_ledger'           => 'Purchase - Electronics',
                'godown_name'            => 'Main Godown',
                'accounting_allocations' => [
                    ['LedgerName' => 'Purchase - Electronics', 'LedgerGroup' => 'Purchase Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 18, 'Amount' => 130000],
                ],
            ]
        );
        $this->le($tid, $v[4005], $ledgers[401],  130000, true,  false, []);
        $this->le($tid, $v[4005], $ledgers[702],   23400, true,  false, []);
        $this->le($tid, $v[4005], $ledgers[202], 153400, false, true,  [['AgstType' => 'New Ref', 'Reference' => 'PV/25-26/002', 'CreditPeriod' => '30 Days', 'Amount' => 153400]]);

        // ── RC/25-26/001 — Rahul full receipt (HDFC Bank) ────────────────
        // Fully settles SV/25-26/001 ₹1,77,000
        $v[4006] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4006],
            [
                'alter_id'              => 4006,
                'voucher_type'          => 'Receipt',
                'voucher_base_type'     => 'Receipt',
                'voucher_number'        => 'RC/25-26/001',
                'voucher_date'          => '2025-05-02',
                'party_name'            => 'Rahul Enterprises',
                'party_tally_ledger_id' => $ledgers[101]->id,
                'voucher_total'         => 177000,
                'is_invoice'            => false,
                'is_deleted'            => false,
                'narration'             => 'Receipt from Rahul Enterprises against SV/25-26/001',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        $this->le($tid, $v[4006], $ledgers[501],  177000, true,  false, []);
        $this->le($tid, $v[4006], $ledgers[101], 177000, false, true,  [['AgstType' => 'Agst Ref', 'Reference' => 'SV/25-26/001', 'CreditPeriod' => '2-Apr-25', 'Amount' => 177000]]);

        // ── RC/25-26/002 — Priya partial receipt ─────────────────────────
        // Partial ₹50,000 against SV/25-26/002 (₹1,88,800)
        $v[4007] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4007],
            [
                'alter_id'              => 4007,
                'voucher_type'          => 'Receipt',
                'voucher_base_type'     => 'Receipt',
                'voucher_number'        => 'RC/25-26/002',
                'voucher_date'          => '2025-04-30',
                'party_name'            => 'Priya Trading Co.',
                'party_tally_ledger_id' => $ledgers[102]->id,
                'voucher_total'         => 50000,
                'is_invoice'            => false,
                'is_deleted'            => false,
                'narration'             => 'Advance receipt from Priya Trading Co. against SV/25-26/002',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        $this->le($tid, $v[4007], $ledgers[501],   50000, true,  false, []);
        $this->le($tid, $v[4007], $ledgers[102], 50000, false, true,  [['AgstType' => 'Agst Ref', 'Reference' => 'SV/25-26/002', 'CreditPeriod' => '5-Apr-25', 'Amount' => 50000]]);

        // ── RC/25-26/003 — Suresh full receipt ───────────────────────────
        $v[4008] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4008],
            [
                'alter_id'              => 4008,
                'voucher_type'          => 'Receipt',
                'voucher_base_type'     => 'Receipt',
                'voucher_number'        => 'RC/25-26/003',
                'voucher_date'          => '2025-05-05',
                'party_name'            => 'Suresh & Sons',
                'party_tally_ledger_id' => $ledgers[103]->id,
                'voucher_total'         => 70800,
                'is_invoice'            => false,
                'is_deleted'            => false,
                'narration'             => 'Full receipt from Suresh & Sons against SV/25-26/003',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        $this->le($tid, $v[4008], $ledgers[501],   70800, true,  false, []);
        $this->le($tid, $v[4008], $ledgers[103], 70800, false, true,  [['AgstType' => 'Agst Ref', 'Reference' => 'SV/25-26/003', 'CreditPeriod' => '10-Apr-25', 'Amount' => 70800]]);

        // ── PY/25-26/001 — Kapoor Suppliers full payment ─────────────────
        $v[4009] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4009],
            [
                'alter_id'              => 4009,
                'voucher_type'          => 'Payment',
                'voucher_base_type'     => 'Payment',
                'voucher_number'        => 'PY/25-26/001',
                'voucher_date'          => '2025-04-15',
                'party_name'            => 'Kapoor Suppliers',
                'party_tally_ledger_id' => $ledgers[201]->id,
                'voucher_total'         => 212400,
                'is_invoice'            => false,
                'is_deleted'            => false,
                'narration'             => 'Payment to Kapoor Suppliers against PV/25-26/001',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        $this->le($tid, $v[4009], $ledgers[201],  212400, true,  true,  [['AgstType' => 'Agst Ref', 'Reference' => 'PV/25-26/001', 'CreditPeriod' => '3-Apr-25', 'Amount' => 212400]]);
        $this->le($tid, $v[4009], $ledgers[501], 212400, false, false, []);

        // ── CN/25-26/001 — Priya returns 1 Samsung (credit note) ─────────
        // ₹80,000 + IGST ₹14,400 = ₹94,400 — settles ₹94,400 of SV/25-26/002
        $v[4010] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4010],
            [
                'alter_id'              => 4010,
                'voucher_type'          => 'Credit Note',
                'voucher_base_type'     => 'CreditNote',
                'voucher_number'        => 'CN/25-26/001',
                'voucher_date'          => '2025-04-20',
                'party_name'            => 'Priya Trading Co.',
                'party_tally_ledger_id' => $ledgers[102]->id,
                'voucher_total'         => 94400,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'place_of_supply'       => 'Karnataka',
                'buyer_name'            => 'Priya Trading Co.',
                'buyer_gstin'           => '29AACCQ5678E2ZL',
                'buyer_state'           => 'Karnataka',
                'narration'             => 'Return of 1 Samsung Galaxy S24 by Priya Trading Co.',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        // Party Cr (is_deemed_positive=false for CN party); settles against SV/25-26/002
        $this->le($tid, $v[4010], $ledgers[102], 94400, false, true,  [['AgstType' => 'Agst Ref', 'Reference' => 'SV/25-26/002', 'CreditPeriod' => '5-Apr-25', 'Amount' => 94400]]);
        $this->le($tid, $v[4010], $ledgers[301],   80000, true,  false, []);
        $this->le($tid, $v[4010], $ledgers[701],   14400, true,  false, []);

        // Outstanding SV/25-26/002 = 1,88,800 − 50,000 (RC/002) − 94,400 (CN/001) = 44,400

        // ── SV/25-26/004 — Priya Trading, iPhone 15 (inter-state) ────────
        // 1 iPhone 15 × ₹90,000 + IGST 18% ₹16,200 = ₹1,06,200 — OUTSTANDING
        $v[4011] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4011],
            [
                'alter_id'              => 4011,
                'voucher_type'          => 'Sales',
                'voucher_base_type'     => 'Sales',
                'voucher_number'        => 'SV/25-26/004',
                'voucher_date'          => '2025-05-15',
                'party_name'            => 'Priya Trading Co.',
                'party_tally_ledger_id' => $ledgers[102]->id,
                'voucher_total'         => 106200,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'place_of_supply'       => 'Karnataka',
                'buyer_name'            => 'Priya Trading Co.',
                'buyer_gstin'           => '29AACCQ5678E2ZL',
                'buyer_gst_registration_type' => 'Regular',
                'buyer_state'           => 'Karnataka',
                'buyer_country'         => 'India',
                'buyer_address'         => '45 MG Road, Bangalore - 560001',
                'buyer_mobile'          => '9123456780',
                'buyer_email'           => 'accounts@priyatrading.com',
                'narration'             => 'Sale of iPhone 15 to Priya Trading Co.',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4011]->id, 'tally_stock_item_id' => $stockItems[3004]->id],
            [
                'tenant_id'              => $tid,
                'stock_item_name'        => 'iPhone 15',
                'hsn_code'               => '85171200',
                'unit'                   => 'Nos',
                'igst_rate'              => 18,
                'is_deemed_positive'     => false,
                'actual_qty'             => 1,
                'billed_qty'             => 1,
                'rate'                   => 90000,
                'discount_percent'       => 0,
                'amount'                 => 90000,
                'tax_amount'             => 16200,
                'sales_ledger'           => 'Sales - Electronics',
                'godown_name'            => 'Main Godown',
                'accounting_allocations' => [
                    ['LedgerName' => 'Sales - Electronics', 'LedgerGroup' => 'Sales Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 18, 'Amount' => 90000],
                ],
            ]
        );
        $this->le($tid, $v[4011], $ledgers[102],  106200, true,  true,  [['AgstType' => 'New Ref', 'Reference' => 'SV/25-26/004', 'CreditPeriod' => '45 Days', 'Amount' => 106200]]);
        $this->le($tid, $v[4011], $ledgers[301], 90000, false, false, []);
        $this->le($tid, $v[4011], $ledgers[701], 16200, false, false, []);

        // ── SV/25-26/005 — Meenakshi Retail, Tamil Nadu (inter-state) ────
        // 10 Wireless Keyboards × ₹2,800 = ₹28,000 + IGST 18% ₹5,040 = ₹33,040 — OUTSTANDING
        $v[4012] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4012],
            [
                'alter_id'              => 4012,
                'voucher_type'          => 'Sales',
                'voucher_base_type'     => 'Sales',
                'voucher_number'        => 'SV/25-26/005',
                'voucher_date'          => '2025-05-20',
                'party_name'            => 'Meenakshi Retail',
                'party_tally_ledger_id' => $ledgers[104]->id,
                'voucher_total'         => 33040,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'place_of_supply'       => 'Tamil Nadu',
                'buyer_name'            => 'Meenakshi Retail Pvt Ltd',
                'buyer_gstin'           => '33AABPM5678K1ZL',
                'buyer_gst_registration_type' => 'Regular',
                'buyer_state'           => 'Tamil Nadu',
                'buyer_country'         => 'India',
                'buyer_address'         => '22 Anna Salai, Chennai - 600001',
                'buyer_mobile'          => '9445566778',
                'buyer_email'           => 'accounts@meenakshiretail.in',
                'narration'             => 'Sale of Wireless Keyboards to Meenakshi Retail',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        TallyVoucherInventoryEntry::firstOrCreate(
            ['tally_voucher_id' => $v[4012]->id, 'tally_stock_item_id' => $stockItems[3010]->id],
            [
                'tenant_id'              => $tid,
                'stock_item_name'        => 'Wireless Keyboard',
                'hsn_code'               => '84716060',
                'unit'                   => 'Nos',
                'igst_rate'              => 18,
                'is_deemed_positive'     => false,
                'actual_qty'             => 10,
                'billed_qty'             => 10,
                'rate'                   => 2800,
                'discount_percent'       => 0,
                'amount'                 => 28000,
                'tax_amount'             => 5040,
                'sales_ledger'           => 'Sales - Accessories',
                'godown_name'            => 'Main Godown',
                'accounting_allocations' => [
                    ['LedgerName' => 'Sales - Accessories', 'LedgerGroup' => 'Sales Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 18, 'Amount' => 28000],
                ],
            ]
        );
        $this->le($tid, $v[4012], $ledgers[104],  33040, true,  true,  [['AgstType' => 'New Ref', 'Reference' => 'SV/25-26/005', 'CreditPeriod' => '30 Days', 'Amount' => 33040]]);
        $this->le($tid, $v[4012], $ledgers[302], 28000, false, false, []);
        $this->le($tid, $v[4012], $ledgers[701], 5040, false, false, []);

        // ── DN/25-26/001 — National Goods quality deduction (debit note) ─
        // ₹5,000 + IGST ₹900 = ₹5,900 — reduces PV/25-26/002 outstanding to ₹1,47,500
        $v[4013] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4013],
            [
                'alter_id'              => 4013,
                'voucher_type'          => 'Debit Note',
                'voucher_base_type'     => 'DebitNote',
                'voucher_number'        => 'DN/25-26/001',
                'voucher_date'          => '2025-04-25',
                'party_name'            => 'National Goods Ltd',
                'party_tally_ledger_id' => $ledgers[202]->id,
                'voucher_total'         => 5900,
                'is_invoice'            => true,
                'is_deleted'            => false,
                'narration'             => 'Quality deduction on PV/25-26/002 — damaged stock',
                'is_active'             => true,
                'last_synced_at'        => now()->subMinutes(30),
            ]
        );
        // Debit Note: party entry Dr (reducing payable) is_deemed_positive=true
        $this->le($tid, $v[4013], $ledgers[202],   5900, true,  true,  [['AgstType' => 'Agst Ref', 'Reference' => 'PV/25-26/002', 'CreditPeriod' => '12-Apr-25', 'Amount' => 5900]]);
        $this->le($tid, $v[4013], $ledgers[401], 5000, false, false, []);
        $this->le($tid, $v[4013], $ledgers[702], 900, false, false, []);

        // ── JV/25-26/001 — Input IGST setoff ─────────────────────────────
        $v[4014] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4014],
            [
                'alter_id'       => 4014,
                'voucher_type'   => 'Journal',
                'voucher_base_type' => 'Journal',
                'voucher_number' => 'JV/25-26/001',
                'voucher_date'   => '2025-04-30',
                'voucher_total'  => 15000,
                'is_invoice'     => false,
                'is_deleted'     => false,
                'narration'      => 'Input IGST setoff against Output IGST for April 2025',
                'is_active'      => true,
                'last_synced_at' => now()->subMinutes(30),
            ]
        );
        $this->le($tid, $v[4014], $ledgers[701],  15000, true,  false, []);
        $this->le($tid, $v[4014], $ledgers[702], 15000, false, false, []);

        // ── CT/25-26/001 — Cash withdrawal from HDFC ─────────────────────
        $v[4015] = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 4015],
            [
                'alter_id'       => 4015,
                'voucher_type'   => 'Contra',
                'voucher_base_type' => 'Contra',
                'voucher_number' => 'CT/25-26/001',
                'voucher_date'   => '2025-04-30',
                'voucher_total'  => 25000,
                'is_invoice'     => false,
                'is_deleted'     => false,
                'narration'      => 'Cash withdrawal from HDFC Bank for petty cash',
                'is_active'      => true,
                'last_synced_at' => now()->subMinutes(30),
            ]
        );
        $this->le($tid, $v[4015], $ledgers[601],  25000, true,  false, []);
        $this->le($tid, $v[4015], $ledgers[501], 25000, false, false, []);

        // ── Reports ───────────────────────────────────────────────────────
        foreach ([
            ['trial_balance',     '2025-04-01', '2025-04-30', ['debit_total' => 1150000, 'credit_total' => 1150000]],
            ['profit_loss',       '2025-04-01', '2025-04-30', ['gross_profit' => 118000, 'net_profit' => 98000, 'sales' => 418000, 'purchase' => 310000]],
            ['balance_sheet',     '2025-04-01', '2025-04-30', ['total_assets' => 1800000, 'total_liabilities' => 900000, 'net_worth' => 900000]],
            ['sales_register',    '2025-04-01', '2025-04-30', ['total_sales' => 430800, 'total_tax' => 62100, 'net_sales' => 368700, 'voucher_count' => 3]],
            ['purchase_register', '2025-04-01', '2025-04-30', ['total_purchase' => 365800, 'total_tax' => 55800, 'net_purchase' => 310000, 'voucher_count' => 2]],
        ] as [$type, $from, $to, $data]) {
            TallyReport::firstOrCreate(
                ['tenant_id' => $tid, 'report_type' => $type, 'period_to' => $to],
                ['period_from' => $from, 'data' => $data, 'generated_at' => now()->subHours(2), 'synced_at' => now()->subHours(2)]
            );
        }

        // ── Sync Logs ─────────────────────────────────────────────────────
        foreach ([
            ['ledger_groups',       'inbound', 'success', false, 28, 0,  0, 0, 0, null],
            ['ledgers',             'inbound', 'success', false, 17, 0,  0, 0, 0, null],
            ['stock_groups',        'inbound', 'success', false,  5, 0,  0, 0, 0, null],
            ['stock_categories',    'inbound', 'success', false,  3, 0,  0, 0, 0, null],
            ['stock_items',         'inbound', 'success', false, 10, 0,  0, 0, 0, null],
            ['vouchers_sales',      'inbound', 'success', false,  5, 0,  0, 0, 0, null],
            ['vouchers_purchase',   'inbound', 'success', false,  2, 0,  0, 0, 0, null],
            ['vouchers_receipt',    'inbound', 'success', false,  3, 0,  0, 0, 0, null],
            ['vouchers_payment',    'inbound', 'success', false,  1, 0,  0, 0, 0, null],
            ['vouchers_creditnote', 'inbound', 'success', false,  1, 0,  0, 0, 0, null],
            ['vouchers_debitnote',  'inbound', 'success', false,  1, 0,  0, 0, 0, null],
            ['vouchers_journal',    'inbound', 'success', false,  1, 0,  0, 0, 0, null],
            ['vouchers_contra',     'inbound', 'success', false,  1, 0,  0, 0, 0, null],
            ['ledgers',             'inbound', 'success', false,  0, 3, 14, 0, 0, null],
            ['stock_items',         'inbound', 'success', false,  0, 4,  6, 0, 0, null],
            ['vouchers_sales',      'inbound', 'success', false,  1, 1,  0, 0, 0, null],
            ['statutory_masters',   'inbound', 'success', false,  8, 0,  0, 0, 0, null],
            ['employee_groups',     'inbound', 'success', false,  5, 0,  0, 0, 0, null],
            ['pay_heads',           'inbound', 'success', false, 10, 0,  0, 0, 0, null],
            ['attendance_types',    'inbound', 'success', false,  7, 0,  0, 0, 0, null],
            ['employees',           'inbound', 'success', false,  5, 0,  0, 0, 0, null],
            ['vouchers_payroll',    'inbound', 'success', false,  1, 0,  0, 0, 0, null],
            ['manual_trigger',      'inbound', 'success', true,   0, 0,  0, 0, 0, null],
            ['ledgers',             'inbound', 'failed',  false,  0, 0,  0, 2, 0, 'Duplicate tally_id detected for tenant'],
        ] as $i => [$entity, $dir, $status, $manual, $created, $updated, $skipped, $failed, $deleted, $error]) {
            TallySyncLog::create([
                'tenant_id'          => $tid,
                'entity'             => $entity,
                'direction'          => $dir,
                'status'             => $status,
                'triggered_manually' => $manual,
                'records_created'    => $created,
                'records_updated'    => $updated,
                'records_skipped'    => $skipped,
                'records_failed'     => $failed,
                'records_deleted'    => $deleted,
                'error_message'      => $error,
                'started_at'         => now()->subDays(1)->addMinutes($i * 3),
                'completed_at'       => $status === 'failed' ? null : now()->subDays(1)->addMinutes($i * 3 + 1),
            ]);
        }

        // ── Statutory Masters ─────────────────────────────────────────────
        foreach ([
            [5001, 'GST Registration - Delhi',        'GST', '07AABCT1234A1ZK', '07', 'Regular',       'AABCT1234A', null,         '2017-07-01', ['GSTRate' => 18, 'FilingFrequency' => 'Monthly']],
            [5002, 'GST Registration - Karnataka',    'GST', '29AABCT1234A2ZK', '29', 'Regular',       'AABCT1234A', null,         '2017-07-01', ['GSTRate' => 18, 'FilingFrequency' => 'Monthly']],
            [5003, 'TDS - Section 194C (Contractor)', 'TDS', 'TDS-194C',        null, 'TDS Deductor',  null,         'DELT12345A', '2023-04-01', ['Section' => '194C', 'Rate' => 1, 'ThresholdLimit' => 30000]],
            [5004, 'TDS - Section 194J (Prof. Svc)',  'TDS', 'TDS-194J',        null, 'TDS Deductor',  null,         'DELT12345A', '2023-04-01', ['Section' => '194J', 'Rate' => 10, 'ThresholdLimit' => 30000]],
            [5005, 'TCS - Section 206C',              'TCS', 'TCS-206C',        null, 'TCS Collector', null,         'DELT12345A', '2023-04-01', ['Section' => '206C', 'Rate' => 0.1]],
            [5006, 'PF Registration',                 'PF',  'PF/DL/12345/001', null, 'Employer',      null,         null,         '2020-01-01', ['EmployerRate' => 12, 'EmployeeRate' => 12, 'AdminCharge' => 0.5]],
            [5007, 'ESI Registration',                'ESI', 'ESI/31/12345/001',null, 'Employer',      null,         null,         '2020-01-01', ['EmployerRate' => 3.25, 'EmployeeRate' => 0.75]],
            [5008, 'Professional Tax - Delhi',        'PT',  'PT/DL/2025/001',  '07', 'PT Deductor',   null,         null,         '2025-04-01', ['MonthlyLimit' => 15000, 'TaxAmount' => 200]],
        ] as [$id, $name, $type, $regNo, $stateCode, $regType, $pan, $tan, $appFrom, $details]) {
            TallyStatutoryMaster::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                [
                    'alter_id'            => $id,
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
        foreach ([
            [6001, 'Management'],
            [6002, 'Operations'],
            [6003, 'Sales & Marketing'],
            [6004, 'Technology'],
            [6005, 'Finance & Accounts'],
        ] as [$id, $name]) {
            $empGroups[$id] = TallyEmployeeGroup::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                ['alter_id' => $id, 'name' => $name, 'under' => null, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
            );
        }

        // ── Pay Heads ─────────────────────────────────────────────────────
        foreach ([
            [7001, 'Basic Salary',         'Earning',                              'Salary Payable', 'On Attendance',     'Monthly'],
            [7002, 'House Rent Allowance', 'Earning',                              'Salary Payable', 'As Computed Value', 'Monthly'],
            [7003, 'Conveyance Allowance', 'Earning',                              'Salary Payable', 'Fixed',             'Monthly'],
            [7004, 'Special Allowance',    'Earning',                              'Salary Payable', 'As Computed Value', 'Monthly'],
            [7005, 'PF - Employee',        "Employees' Statutory Deductions",      'PF Payable',     'As Computed Value', 'Monthly'],
            [7006, 'PF - Employer',        "Employer's Statutory Contributions",   'PF Payable',     'As Computed Value', 'Monthly'],
            [7007, 'ESI - Employee',       "Employees' Statutory Deductions",      'ESI Payable',    'As Computed Value', 'Monthly'],
            [7008, 'ESI - Employer',       "Employer's Statutory Contributions",   'ESI Payable',    'As Computed Value', 'Monthly'],
            [7009, 'TDS on Salary',        "Employees' Statutory Deductions",      'TDS Payable',    'As Computed Value', 'Monthly'],
            [7010, 'Professional Tax',     "Employees' Statutory Deductions",      'PT Payable',     'Fixed',             'Monthly'],
        ] as [$id, $name, $payType, $parentGroup, $calcType, $calcPeriod]) {
            TallyPayHead::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                [
                    'alter_id'           => $id,
                    'name'               => $name,
                    'pay_type'           => $payType,
                    'income_type'        => null,
                    'parent_group'       => $parentGroup,
                    'calculation_type'   => $calcType,
                    'calculation_period' => $calcPeriod,
                    'is_active'          => true,
                    'last_synced_at'     => now()->subHours(1),
                ]
            );
        }

        // ── Attendance Types ──────────────────────────────────────────────
        foreach ([
            [8001, 'Present',        'Attendance',        'Days'],
            [8002, 'Casual Leave',   'Leave with Pay',    'Days'],
            [8003, 'Sick Leave',     'Leave with Pay',    'Days'],
            [8004, 'Unpaid Leave',   'Leave without Pay', 'Days'],
            [8005, 'Holiday',        'Attendance',        'Days'],
            [8006, 'Week Off',       'Attendance',        'Days'],
            [8007, 'Overtime Hours', 'Productivity',      'Hours'],
        ] as [$id, $name, $type, $period]) {
            TallyAttendanceType::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                ['alter_id' => $id, 'name' => $name, 'attendance_type' => $type, 'attendance_period' => $period, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
            );
        }

        // ── Employees ─────────────────────────────────────────────────────
        $employees = [];
        foreach ([
            [9001, 'Arjun Mehta',   'EMP001', 6001, 'Director',          '2019-06-01', '1980-03-15', 'Male'],
            [9002, 'Sunita Sharma', 'EMP002', 6004, 'Senior Developer',  '2021-01-15', '1992-07-22', 'Female'],
            [9003, 'Rakesh Gupta',  'EMP003', 6003, 'Sales Manager',     '2020-08-10', '1988-11-05', 'Male'],
            [9004, 'Pooja Nair',    'EMP004', 6005, 'Accountant',        '2022-03-01', '1995-04-18', 'Female'],
            [9005, 'Vikram Singh',  'EMP005', 6002, 'Operations Lead',   '2020-11-20', '1990-09-30', 'Male'],
        ] as [$id, $name, $empNo, $groupId, $designation, $doj, $dob, $gender]) {
            $employees[$name] = TallyEmployee::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $id],
                [
                    'alter_id'        => $id,
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

        // ── Payroll — April 2025 salary run ──────────────────────────────
        $salaryVoucher = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 6001],
            [
                'alter_id'       => 6001,
                'voucher_type'   => 'Payroll',
                'voucher_base_type' => 'Payroll',
                'voucher_number' => 'SAL/25-26/001',
                'voucher_date'   => '2025-04-30',
                'narration'      => 'Salary for the month of April 2025',
                'is_invoice'     => false,
                'is_deleted'     => false,
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]
        );

        foreach ([
            ['Arjun Mehta',   'Management',        [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 80000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 32000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 3200],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -9600],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 105400],
            ['Sunita Sharma', 'Technology',         [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 60000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 24000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -7200],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 78200],
            ['Rakesh Gupta',  'Sales & Marketing', [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 55000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 22000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -6600],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 71800],
            ['Pooja Nair',    'Finance & Accounts',[
                ['PayHeadName' => 'Basic Salary',         'Amount' => 40000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 16000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -4800],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 52600],
            ['Vikram Singh',  'Operations',        [
                ['PayHeadName' => 'Basic Salary',         'Amount' => 50000],
                ['PayHeadName' => 'House Rent Allowance', 'Amount' => 20000],
                ['PayHeadName' => 'Conveyance Allowance', 'Amount' => 1600],
                ['PayHeadName' => 'PF - Employee',        'Amount' => -6000],
                ['PayHeadName' => 'Professional Tax',     'Amount' => -200],
            ], 65400],
        ] as [$empName, $empGroup, $payHeadEntries, $netPayable]) {
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

        // ── Attendance — April 2025 ───────────────────────────────────────
        $attendanceVoucher = TallyVoucher::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 6002],
            [
                'alter_id'       => 6002,
                'voucher_type'   => 'Attendance',
                'voucher_base_type' => 'Attendance',
                'voucher_number' => 'ATT/25-26/001',
                'voucher_date'   => '2025-04-30',
                'narration'      => 'Attendance for the month of April 2025',
                'is_invoice'     => false,
                'is_deleted'     => false,
                'is_active'      => true,
                'last_synced_at' => now()->subHours(1),
            ]
        );

        foreach ([
            ['Arjun Mehta',   'Management',        [['AttendanceType' => 'Present', 'Units' => 25], ['AttendanceType' => 'Casual Leave', 'Units' => 1], ['AttendanceType' => 'Week Off', 'Units' => 4]]],
            ['Sunita Sharma', 'Technology',         [['AttendanceType' => 'Present', 'Units' => 23], ['AttendanceType' => 'Sick Leave',   'Units' => 2], ['AttendanceType' => 'Week Off', 'Units' => 5]]],
            ['Rakesh Gupta',  'Sales & Marketing', [['AttendanceType' => 'Present', 'Units' => 24], ['AttendanceType' => 'Unpaid Leave', 'Units' => 1], ['AttendanceType' => 'Week Off', 'Units' => 5]]],
            ['Pooja Nair',    'Finance & Accounts',[['AttendanceType' => 'Present', 'Units' => 26], ['AttendanceType' => 'Week Off',     'Units' => 4]]],
            ['Vikram Singh',  'Operations',        [['AttendanceType' => 'Present', 'Units' => 22], ['AttendanceType' => 'Casual Leave', 'Units' => 2], ['AttendanceType' => 'Week Off', 'Units' => 6]]],
        ] as [$empName, $empGroup, $entries]) {
            TallyVoucherEmployeeAllocation::firstOrCreate(
                ['tally_voucher_id' => $attendanceVoucher->id, 'employee_name' => $empName],
                [
                    'tenant_id'         => $tid,
                    'tally_employee_id' => $employees[$empName]?->id,
                    'employee_group'    => $empGroup,
                    'entries'           => $entries,
                    'net_payable'       => null,
                ]
            );
        }

        // ── BlueStar ISP dataset (Sales - Lease Line) ─────────────────────
        // Two monthly invoices; first fully paid, second outstanding.

        $bluestarLedger = TallyLedger::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 105],
            [
                'alter_id'              => 105,
                'ledger_name'           => 'BlueStar Technologies',
                'group_name'            => 'Sundry Debtors',
                'gstin_number'          => '27AABCT1234A1Z5',
                'gst_registration_type' => 'Regular',
                'mailing_name'          => 'BlueStar Technologies Pvt Ltd',
                'contact_person'        => 'Rajesh Kumar',
                'contact_person_email'  => 'rajesh@bluestar.in',
                'contact_person_mobile' => '9876543210',
                'mobile_number'         => '9876543210',
                'addresses'             => [['Address' => '123 MG Road, Bangalore - 560001']],
                'state_name'            => 'Karnataka',
                'country_name'          => 'India',
                'pin_code'              => '560001',
                'credit_period'         => 30,
                'credit_limit'          => 500000,
                'opening_balance'       => 0,
                'opening_balance_type'  => 'Dr',
                'is_bill_wise_on'       => true,
                'inventory_affected'    => false,
                'is_active'             => true,
                'last_synced_at'        => now()->subHours(1),
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
                'is_bill_wise_on'    => false,
                'inventory_affected' => false,
                'is_active'          => true,
                'last_synced_at'     => now()->subHours(1),
            ]
        );
        $ledgers[305] = $salesLeaseLedger;

        TallyStockGroup::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 10],
            ['alter_id' => 10, 'name' => 'Network Equipment', 'parent' => null, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
        );
        TallyStockCategory::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 5],
            ['alter_id' => 5, 'name' => 'Lease Line Services', 'parent' => null, 'is_active' => true, 'last_synced_at' => now()->subHours(1)]
        );

        $leaseLineItem = TallyStockItem::firstOrCreate(
            ['tenant_id' => $tid, 'tally_id' => 201],
            [
                'alter_id'          => 201,
                'name'              => '30Mbps Lease Line',
                'description'       => 'Dedicated internet lease line 30Mbps',
                'stock_group_id'    => 10,
                'stock_group_name'  => 'Network Equipment',
                'stock_category_id' => 5,
                'category_name'     => 'Lease Line Services',
                'unit_name'         => 'Nos',
                'is_gst_applicable' => true,
                'taxability'        => 'Taxable',
                'calculation_type'  => 'On Value',
                'igst_rate'         => 18,
                'cgst_rate'         => 9,
                'sgst_rate'         => 9,
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

        // Invoice 1 (Apr 2025): fully paid. Invoice 2 (May 2025): outstanding.
        foreach ([
            [5001, 'INV/25-26/001', '2025-04-01', 'Monthly lease line for April 2025',  true,  17700],
            [5002, 'INV/25-26/002', '2025-05-01', 'Monthly lease line for May 2025',    false, 17700],
        ] as [$tallyId, $number, $date, $narration, $fullyPaid, $total]) {
            $lsv = TallyVoucher::firstOrCreate(
                ['tenant_id' => $tid, 'tally_id' => $tallyId],
                [
                    'alter_id'              => $tallyId,
                    'voucher_type'          => 'Sales',
                    'voucher_base_type'     => 'Sales',
                    'voucher_number'        => $number,
                    'voucher_date'          => $date,
                    'party_name'            => 'BlueStar Technologies',
                    'party_tally_ledger_id' => $bluestarLedger->id,
                    'voucher_total'         => $total,
                    'is_invoice'            => true,
                    'is_deleted'            => false,
                    'place_of_supply'       => 'Karnataka',
                    'buyer_name'            => 'BlueStar Technologies Pvt Ltd',
                    'buyer_gstin'           => '27AABCT1234A1Z5',
                    'buyer_state'           => 'Karnataka',
                    'buyer_address'         => '123 MG Road, Bangalore - 560001',
                    'buyer_mobile'          => '9876543210',
                    'buyer_email'           => 'rajesh@bluestar.in',
                    'narration'             => $narration,
                    'is_active'             => true,
                    'last_synced_at'        => now()->subMinutes(30),
                ]
            );

            TallyVoucherInventoryEntry::firstOrCreate(
                ['tally_voucher_id' => $lsv->id, 'tally_stock_item_id' => $leaseLineItem->id],
                [
                    'tenant_id'              => $tid,
                    'stock_item_name'        => '30Mbps Lease Line',
                    'hsn_code'               => '998422',
                    'unit'                   => 'Nos',
                    'igst_rate'              => 18,
                    'is_deemed_positive'     => false,
                    'actual_qty'             => 1,
                    'billed_qty'             => 1,
                    'rate'                   => 15000,
                    'discount_percent'       => 0,
                    'amount'                 => 15000,
                    'tax_amount'             => 2700,
                    'sales_ledger'           => 'Sales - Lease Line',
                    'accounting_allocations' => [
                        ['LedgerName' => 'Sales - Lease Line', 'LedgerGroup' => 'Sales Accounts', 'GSTClassification' => 'Taxable', 'IGSTRate' => 18, 'Amount' => 15000],
                    ],
                ]
            );

            $billRef = $fullyPaid
                ? [['AgstType' => 'New Ref', 'Reference' => $number, 'CreditPeriod' => '30 Days', 'Amount' => 17700]]
                : [['AgstType' => 'New Ref', 'Reference' => $number, 'CreditPeriod' => '30 Days', 'Amount' => 17700]];

            $this->le($tid, $lsv, $bluestarLedger,   17700, true,  true,  $billRef);
            $this->le($tid, $lsv, $salesLeaseLedger, 15000, false, false, []);
            $this->le($tid, $lsv, $ledgers[701], 2700, false, false, []);

            $invoice = Invoice::firstOrCreate(
                ['tally_voucher_id' => $lsv->id, 'tenant_id' => $tid],
                [
                    'tenant_id'      => $tid,
                    'client_id'      => $bluestarClient->id,
                    'invoice_number' => $number,
                    'issue_date'     => $date,
                    'due_date'       => date('Y-m-d', strtotime($date . ' +30 days')),
                    'status'         => $fullyPaid ? 'paid' : 'sent',
                    'subtotal'       => 15000,
                    'tax_amount'     => 2700,
                    'total'          => 17700,
                    'currency'       => 'INR',
                    'notes'          => $narration,
                    'amount_paid'    => $fullyPaid ? 17700 : 0,
                    'amount_due'     => $fullyPaid ? 0 : 17700,
                ]
            );
            $lsv->update(['mapped_invoice_id' => $invoice->id]);

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

            // Receipt for the paid invoice
            if ($fullyPaid) {
                $rcv = TallyVoucher::firstOrCreate(
                    ['tenant_id' => $tid, 'tally_id' => $tallyId + 100],
                    [
                        'alter_id'       => $tallyId + 100,
                        'voucher_type'   => 'Receipt',
                        'voucher_base_type' => 'Receipt',
                        'voucher_number' => 'RC/25-26/' . str_pad($tallyId - 5000 + 3, 3, '0', STR_PAD_LEFT),
                        'voucher_date'   => date('Y-m-d', strtotime($date . ' +28 days')),
                        'party_name'     => 'BlueStar Technologies',
                        'party_tally_ledger_id' => $bluestarLedger->id,
                        'voucher_total'  => 17700,
                        'is_invoice'     => false,
                        'is_deleted'     => false,
                        'narration'      => 'Receipt from BlueStar Technologies against ' . $number,
                        'is_active'      => true,
                        'last_synced_at' => now()->subMinutes(30),
                    ]
                );
                $invDate = date('j-M-y', strtotime($date));
                $this->le($tid, $rcv, $ledgers[501],   17700, true,  false, []);
                $this->le($tid, $rcv, $bluestarLedger, 17700, false, true,  [['AgstType' => 'Agst Ref', 'Reference' => $number, 'CreditPeriod' => $invDate, 'Amount' => 17700]]);
            }
        }

        $this->command->info('TallySeeder: FY 2025-26 dataset seeded — 17 ledgers, 5 sales vouchers, 2 purchases, 3 receipts, 1 payment, 1 credit note, 1 debit note, 1 journal, 1 contra + BlueStar ISP data. Outstanding bills: Priya ₹44,400 + ₹1,06,200 | Meenakshi ₹33,040 | National Goods ₹1,47,500.');
    }

    private function le(string $tid, TallyVoucher $voucher, TallyLedger $ledger, float $amount, bool $isDeemedPositive, bool $isParty, array $billsAllocation): void
    {
        TallyVoucherLedgerEntry::firstOrCreate(
            ['tally_voucher_id' => $voucher->id, 'tally_ledger_id' => $ledger->id],
            [
                'tenant_id'          => $tid,
                'ledger_name'        => $ledger->ledger_name,
                'ledger_group'       => $ledger->group_name,
                'ledger_amount'      => $amount,
                'is_deemed_positive' => $isDeemedPositive,
                'is_party_ledger'    => $isParty,
                'bills_allocation'   => $billsAllocation ?: null,
            ]
        );
    }
}
