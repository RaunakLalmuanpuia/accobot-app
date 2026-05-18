<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TallyAttendanceType;
use App\Models\TallyCompany;
use App\Models\TallyGodown;
use App\Models\TallyUnit;
use App\Models\TallyStockCategory;
use App\Models\TallyStockGroup;
use App\Models\TallyEmployee;
use App\Models\TallyEmployeeGroup;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
use App\Models\TallyPayHead;
use App\Models\TallyStatutoryMaster;
use App\Models\TallyStockItem;
use App\Models\TallyVoucher;
use App\Services\Tally\TallyOutboundFormatter;
use Illuminate\Support\Facades\DB;

class TallyDataController extends Controller
{
    private function queueMap(string $tenantId, string $entityClass): array
    {
        return DB::table('tally_outbound_queue')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityClass)
            ->pluck('status', 'entity_id')
            ->all();
    }

    private function addSyncStatus($records, array $map)
    {
        return $records->each(function ($r) use ($map) {
            $r->sync_status = $map[$r->id] ?? ($r->tally_id ? 'synced' : 'local');
        });
    }

    public function ledgerGroups(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyLedgerGroup::class);
        return inertia('Tally/LedgerGroups', [
            'tenant' => $tenant,
            'groups' => $this->addSyncStatus(
                TallyLedgerGroup::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'under_id', 'under_name', 'nature_of_group', 'is_active', 'last_synced_at']),
                $map
            ),
        ]);
    }

    public function ledgers(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyLedger::class);
        return inertia('Tally/Ledgers', [
            'tenant'           => $tenant,
            'ledgers'          => $this->addSyncStatus(
                TallyLedger::where('tenant_id', $tenant->id)
                    ->with(['mappedClient:id,name', 'mappedVendor:id,name'])
                    ->orderBy('ledger_name')
                    ->get([
                        'id', 'tally_id', 'ledger_name', 'group_name', 'parent_group',
                        'is_bill_wise_on', 'inventory_affected', 'is_cost_centres_on',
                        'gstin_number', 'pan_number', 'gst_type_ledger', 'gst_registration_type',
                        'gst_applicable_from', 'is_gst_applicable',
                        'is_sez_party', 'is_transporter', 'is_other_territory_assessee',
                        'appropriate_for',
                        'pan_applicable_from', 'name_on_pan',
                        'is_tds_applicable', 'tds_deductee_type', 'is_tcs_applicable',
                        'is_rcm_applicable',
                        'mailing_name', 'mobile_number', 'contact_person',
                        'contact_person_email', 'contact_person_email_cc', 'contact_person_fax',
                        'contact_person_website', 'contact_person_mobile',
                        'addresses', 'state_name', 'country_name', 'pin_code',
                        'credit_period', 'credit_limit', 'is_credit_days_check_on',
                        'opening_balance', 'opening_balance_type',
                        'type_of_interest_on', 'is_interest_on', 'is_interest_on_bill_wise',
                        'override_interest', 'interest_incl_day_of_addition', 'interest_incl_day_of_deduction',
                        'bank_account_holder_name', 'swift_code', 'branch_name', 'bank_bsr_code',
                        'default_transfer_mode', 'is_cheque_printing_enabled',
                        'is_related_party', 'for_payroll',
                        'aliases', 'description', 'notes', 'bank_details', 'bill_allocations',
                        'gst_registration_details', 'cheque_ranges', 'transfer_mode_limits',
                        'interest_collection', 'tds_category_details', 'tcs_category_details',
                        'contact_details',
                        'is_active', 'last_synced_at',
                        'mapped_client_id', 'mapped_vendor_id',
                    ]),
                $map
            ),
            'ledgerGroups' => TallyLedgerGroup::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'under_name']),
        ]);
    }

    public function units(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyUnit::class);
        return inertia('Tally/Units', [
            'tenant' => $tenant,
            'units'  => $this->addSyncStatus(
                TallyUnit::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'symbol', 'formal_name', 'original_name', 'decimal_places', 'uqc', 'is_simple_unit', 'conversion', 'is_active', 'last_synced_at']),
                $map
            ),
        ]);
    }

    public function stockMasters(Tenant $tenant)
    {
        $sgMap  = $this->queueMap($tenant->id, TallyStockGroup::class);
        $scMap  = $this->queueMap($tenant->id, TallyStockCategory::class);
        $unitMap = $this->queueMap($tenant->id, TallyUnit::class);
        return inertia('Tally/StockMasters', [
            'tenant'          => $tenant,
            'stockGroups'     => $this->addSyncStatus(
                TallyStockGroup::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get([
                        'id', 'tally_id', 'name', 'parent_name', 'aliases',
                        'costing_method', 'valuation_method',
                        'is_batch_wise_on', 'is_perishable_on', 'is_addable',
                        'is_active', 'last_synced_at',
                    ]),
                $sgMap
            ),
            'stockCategories' => $this->addSyncStatus(
                TallyStockCategory::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'parent_name', 'aliases', 'is_active', 'last_synced_at']),
                $scMap
            ),
            'godowns'         => TallyGodown::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'tally_id', 'name', 'under', 'guid', 'has_no_space', 'has_no_stock', 'is_external', 'is_internal', 'is_active', 'last_synced_at']),
            'units'           => $this->addSyncStatus(
                TallyUnit::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'symbol', 'formal_name', 'original_name', 'decimal_places', 'uqc', 'is_simple_unit', 'conversion', 'is_active', 'last_synced_at']),
                $unitMap
            ),
        ]);
    }

    public function stockItems(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyStockItem::class);
        return inertia('Tally/StockItems', [
            'tenant'             => $tenant,
            'items'              => $this->addSyncStatus(
                TallyStockItem::where('tenant_id', $tenant->id)
                    ->with(['mappedProduct:id,name'])
                    ->orderBy('name')
                    ->get([
                        'id', 'tally_id', 'name', 'description', 'remarks',
                        'aliases', 'part_nos',
                        'stock_group_name', 'category_name',
                        'unit_name', 'alternate_unit', 'conversion', 'denominator',
                        'reporting_uom',
                        'is_gst_applicable', 'taxability', 'calculation_type',
                        'hsn_code', 'hsn_desc', 'type_of_supply',
                        'igst_rate', 'cgst_rate', 'sgst_rate', 'cess_rate',
                        'tcs_applicable', 'tcs_category',
                        'mrp_rate', 'inclusive_tax', 'modify_mrp_rate', 'calc_on_mrp', 'mrp_incl_of_tax',
                        'basic_rate_of_excise',
                        'costing_method', 'valuation_method',
                        'sales_ledger', 'purchase_ledger',
                        'opening_balance', 'opening_rate', 'opening_value',
                        'closing_balance', 'closing_rate', 'closing_value',
                        'is_batch_wise', 'is_perishable', 'has_mfg_date', 'allow_expired_items',
                        'ignore_batches', 'ignore_godowns', 'ignore_neg_stock',
                        'is_cost_centres_on', 'is_cost_tracking_on',
                        'batch_allocations',
                        'is_active', 'last_synced_at', 'mapped_product_id',
                    ]),
                $map
            ),
            'stockGroupNames'    => TallyStockGroup::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'stockCategoryNames' => TallyStockCategory::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'unitNames'          => TallyUnit::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'godownNames'        => TallyGodown::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['name', 'tally_id']),
            'salesLedgerNames'   => TallyLedger::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('group_name', 'Sales Accounts')
                ->orderBy('ledger_name')
                ->pluck('ledger_name'),
            'purchaseLedgerNames' => TallyLedger::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->where('group_name', 'Purchase Accounts')
                ->orderBy('ledger_name')
                ->pluck('ledger_name'),
        ]);
    }

    public function vouchers(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyVoucher::class);
        return inertia('Tally/Vouchers', [
            'tenant'   => $tenant,
            'vouchers' => $this->addSyncStatus(
                TallyVoucher::where('tenant_id', $tenant->id)
                    ->orderByDesc('voucher_date')
                    ->orderByDesc('id')
                    ->get([
                        'id', 'tally_id', 'voucher_type', 'voucher_base_type',
                        'voucher_number', 'voucher_date', 'party_name', 'voucher_total',
                        'narration', 'is_active', 'last_synced_at',
                    ]),
                $map
            ),
        ]);
    }

    public function voucherCreate(Tenant $tenant)
    {
        return inertia('Tally/VoucherCreate', [
            'tenant'                 => $tenant,
            'ledgers'                => TallyLedger::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('ledger_name')->get(['id', 'ledger_name', 'group_name', 'gstin_number', 'gst_type_ledger', 'gst_registration_type', 'mailing_name', 'mobile_number', 'contact_person_email', 'state_name', 'pin_code', 'country_name', 'addresses']),
            'stockItems'             => TallyStockItem::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'hsn_code', 'unit_name', 'igst_rate', 'cess_rate', 'stock_group_name', 'mrp_rate', 'opening_rate']),
            'godowns'                => TallyGodown::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'nextSalesVoucherNumber' => $this->nextSalesVoucherNumber($tenant->id),
        ]);
    }

    public function voucherEdit(Tenant $tenant, TallyVoucher $voucher)
    {
        $voucher->load([
            'ledgerEntries:id,tally_voucher_id,ledger_name,ledger_group,ledger_amount,is_deemed_positive,is_party_ledger,igst_rate,hsn_code,cess_rate,bills_allocation,bank_allocation_details,category_allocation',
            'inventoryEntries:id,tally_voucher_id,stock_item_name,item_code,group_name,hsn_code,unit,igst_rate,cess_rate,is_deemed_positive,actual_qty,billed_qty,rate,discount_percent,amount,tax_amount,mrp,sales_ledger,godown_name,batch_name,batch_allocations,accounting_allocations',
        ]);
        return inertia('Tally/VoucherEdit', [
            'tenant'     => $tenant,
            'voucher'    => $voucher,
            'ledgers'    => TallyLedger::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('ledger_name')->get(['id', 'ledger_name', 'group_name', 'gstin_number', 'gst_type_ledger', 'gst_registration_type', 'mailing_name', 'mobile_number', 'contact_person_email', 'state_name', 'pin_code', 'country_name', 'addresses']),
            'stockItems' => TallyStockItem::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'hsn_code', 'unit_name', 'igst_rate', 'cess_rate', 'stock_group_name', 'mrp_rate', 'opening_rate']),
            'godowns'    => TallyGodown::where('tenant_id', $tenant->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function nextSalesVoucherNumber(string $tenantId): int
    {
        $max = TallyVoucher::where('tenant_id', $tenantId)
            ->where('voucher_base_type', 'Sales')
            ->pluck('voucher_number')
            ->map(fn ($n) => ctype_digit((string) $n) ? (int) $n : 0)
            ->max() ?? 0;

        return $max + 1;
    }

    public function companies(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyCompany::class);
        return inertia('Tally/Companies', [
            'tenant' => $tenant,
            'items'  => $this->addSyncStatus(
                TallyCompany::where('tenant_id', $tenant->id)
                    ->orderBy('company_name')
                    ->get([
                        'id', 'tally_id', 'company_guid', 'company_name', 'formal_name', 'name_alias',
                        'email', 'phone_number', 'fax_number', 'website', 'mobile_numbers',
                        'address', 'address1', 'address2', 'address3', 'address4', 'address5',
                        'state', 'prior_state', 'country', 'country_isd_code', 'pincode',
                        'branch_name', 'branch_name2', 'connect_name', 'db_name',
                        'company_number', 'statutory_version', 'corporate_identity_no',
                        'tally_serial_no', 'licence_type',
                        'income_tax_number', 'sales_tax_number', 'ta_number',
                        'gst_registration_number', 'gst_registration_type', 'gst_applicability',
                        'eway_bill_applicable_type',
                        'starting_from', 'books_from', 'audited_upto',
                        'this_year_beg', 'this_year_end', 'prev_year_beg', 'prev_year_end',
                        'feature_flags', 'deductor_details',
                    ]),
                $map
            ),
        ]);
    }

    public function statutoryMasters(Tenant $tenant)
    {
        $map = $this->queueMap($tenant->id, TallyStatutoryMaster::class);
        return inertia('Tally/StatutoryMasters', [
            'tenant' => $tenant,
            'items'  => $this->addSyncStatus(
                TallyStatutoryMaster::where('tenant_id', $tenant->id)
                    ->orderBy('statutory_type')
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'statutory_type', 'registration_number', 'state_code',
                           'registration_type', 'pan', 'tan', 'applicable_from', 'is_active', 'last_synced_at']),
                $map
            ),
        ]);
    }

    public function payroll(Tenant $tenant)
    {
        $empMap = $this->queueMap($tenant->id, TallyEmployee::class);
        $egMap  = $this->queueMap($tenant->id, TallyEmployeeGroup::class);
        $phMap  = $this->queueMap($tenant->id, TallyPayHead::class);
        $atMap  = $this->queueMap($tenant->id, TallyAttendanceType::class);
        return inertia('Tally/Payroll', [
            'tenant'          => $tenant,
            'employees'       => $this->addSyncStatus(
                TallyEmployee::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'employee_number', 'parent', 'designation',
                           'employee_function', 'location',
                           'date_of_joining', 'date_of_leaving', 'date_of_birth', 'gender',
                           'father_name', 'spouse_name',
                           'contact_number', 'email_address', 'address', 'aliases',
                           'is_active', 'last_synced_at']),
                $empMap
            ),
            'employeeGroups'  => $this->addSyncStatus(
                TallyEmployeeGroup::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'under', 'cost_centre_category', 'aliases', 'is_active', 'last_synced_at']),
                $egMap
            ),
            'payHeads'        => $this->addSyncStatus(
                TallyPayHead::where('tenant_id', $tenant->id)
                    ->orderBy('pay_type')
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'pay_type', 'income_type', 'parent_group',
                           'calculation_type', 'leave_type', 'calculation_period', 'is_active', 'last_synced_at']),
                $phMap
            ),
            'attendanceTypes' => $this->addSyncStatus(
                TallyAttendanceType::where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'tally_id', 'name', 'under', 'attendance_type', 'attendance_period', 'aliases', 'is_active', 'last_synced_at']),
                $atMap
            ),
        ]);
    }

    public function voucherShow(Tenant $tenant, TallyVoucher $voucher)
    {
        abort_unless($voucher->tenant_id === $tenant->id, 404);

        $voucher->load([
            'inventoryEntries.stockItem:id,name,unit_name,hsn_code',
            'ledgerEntries.ledger:id,ledger_name,group_name',
            'partyLedger:id,ledger_name,group_name,gstin_number,state_name',
            'mappedInvoice:id,invoice_number',
        ]);

        $formatter = app(TallyOutboundFormatter::class);
        $col       = collect([$voucher]);
        $payload   = match ($voucher->voucher_base_type) {
            'Sales'      => $formatter->formatSalesVouchers($col),
            'Purchase'   => $formatter->formatPurchaseVouchers($col),
            'Receipt'    => $formatter->formatReceiptVouchers($col),
            'Payment'    => $formatter->formatPaymentVouchers($col),
            'Contra'     => $formatter->formatContraVouchers($col),
            'Journal'    => $formatter->formatJournalVouchers($col),
            'CreditNote' => $formatter->formatCreditNoteVouchers($col),
            'DebitNote'  => $formatter->formatDebitNoteVouchers($col),
            default      => [],
        };

        return inertia('Tally/VoucherShow', [
            'tenant'  => $tenant,
            'voucher' => $voucher,
            'payload' => $payload[0] ?? null,
        ]);
    }
}
