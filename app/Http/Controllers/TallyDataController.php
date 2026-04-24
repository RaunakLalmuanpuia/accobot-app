<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TallyAttendanceType;
use App\Models\TallyGodown;
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

class TallyDataController extends Controller
{
    public function ledgerGroups(Tenant $tenant)
    {
        return inertia('Tally/LedgerGroups', [
            'tenant' => $tenant,
            'groups' => TallyLedgerGroup::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'under_id', 'under_name', 'nature_of_group', 'is_active', 'last_synced_at']),
        ]);
    }

    public function ledgers(Tenant $tenant)
    {
        return inertia('Tally/Ledgers', [
            'tenant'           => $tenant,
            'ledgers'          => TallyLedger::where('tenant_id', $tenant->id)
                ->with([
                    'mappedClient:id,name',
                    'mappedVendor:id,name',
                ])
                ->orderBy('ledger_name')
                ->get([
                    'id', 'tally_id', 'ledger_name', 'group_name', 'parent_group',
                    'gstin_number', 'pan_number', 'gst_type', 'state_name', 'country_name',
                    'mobile_number', 'opening_balance', 'opening_balance_type', 'credit_limit',
                    'bank_details', 'aliases', 'is_active', 'last_synced_at',
                    'mapped_client_id', 'mapped_vendor_id',
                ]),
            'ledgerGroupNames' => TallyLedgerGroup::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function stockMasters(Tenant $tenant)
    {
        return inertia('Tally/StockMasters', [
            'tenant'          => $tenant,
            'stockGroups'     => TallyStockGroup::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'parent', 'aliases', 'is_active', 'last_synced_at']),
            'stockCategories' => TallyStockCategory::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'parent', 'aliases', 'is_active', 'last_synced_at']),
            'godowns'         => TallyGodown::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'under', 'guid', 'is_active', 'last_synced_at']),
        ]);
    }

    public function stockItems(Tenant $tenant)
    {
        return inertia('Tally/StockItems', [
            'tenant'             => $tenant,
            'items'              => TallyStockItem::where('tenant_id', $tenant->id)
                ->with(['mappedProduct:id,name'])
                ->orderBy('name')
                ->get([
                    'id', 'name', 'stock_group_name', 'category_name', 'unit_name',
                    'hsn_code', 'igst_rate', 'cgst_rate', 'sgst_rate',
                    'mrp_rate', 'opening_balance', 'closing_balance',
                    'closing_value', 'is_active', 'last_synced_at', 'mapped_product_id',
                ]),
            'stockGroupNames'    => TallyStockGroup::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
            'stockCategoryNames' => TallyStockCategory::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->pluck('name'),
        ]);
    }

    public function vouchers(Tenant $tenant)
    {
        return inertia('Tally/Vouchers', [
            'tenant'   => $tenant,
            'vouchers' => TallyVoucher::where('tenant_id', $tenant->id)
                ->orderByDesc('voucher_date')
                ->orderByDesc('id')
                ->get([
                    'id', 'voucher_type', 'voucher_number', 'voucher_date',
                    'party_name', 'voucher_total', 'is_invoice', 'is_deleted',
                    'narration', 'is_active', 'last_synced_at', 'mapped_invoice_id',
                ]),
        ]);
    }

    public function statutoryMasters(Tenant $tenant)
    {
        return inertia('Tally/StatutoryMasters', [
            'tenant' => $tenant,
            'items'  => TallyStatutoryMaster::where('tenant_id', $tenant->id)
                ->orderBy('statutory_type')
                ->orderBy('name')
                ->get(['id', 'name', 'statutory_type', 'registration_number', 'state_code',
                       'registration_type', 'pan', 'tan', 'applicable_from', 'is_active', 'last_synced_at']),
        ]);
    }

    public function payroll(Tenant $tenant)
    {
        return inertia('Tally/Payroll', [
            'tenant'          => $tenant,
            'employees'       => TallyEmployee::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'employee_number', 'parent', 'designation',
                       'location', 'date_of_joining', 'gender', 'father_name', 'spouse_name',
                       'is_active', 'last_synced_at']),
            'employeeGroups'  => TallyEmployeeGroup::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'under', 'cost_centre_category', 'is_active', 'last_synced_at']),
            'payHeads'        => TallyPayHead::where('tenant_id', $tenant->id)
                ->orderBy('pay_type')
                ->orderBy('name')
                ->get(['id', 'name', 'pay_type', 'income_type', 'parent_group',
                       'calculation_type', 'leave_type', 'calculation_period', 'is_active', 'last_synced_at']),
            'attendanceTypes' => TallyAttendanceType::where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name', 'attendance_type', 'attendance_period', 'is_active', 'last_synced_at']),
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

        return inertia('Tally/VoucherShow', [
            'tenant'  => $tenant,
            'voucher' => $voucher,
        ]);
    }
}
