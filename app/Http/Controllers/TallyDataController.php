<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TallyLedger;
use App\Models\TallyLedgerGroup;
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
                ->get(['id', 'name', 'under_name', 'nature_of_group', 'is_revenue', 'affects_gross', 'is_addable', 'is_active', 'last_synced_at']),
        ]);
    }

    public function ledgers(Tenant $tenant)
    {
        return inertia('Tally/Ledgers', [
            'tenant'  => $tenant,
            'ledgers' => TallyLedger::where('tenant_id', $tenant->id)
                ->with([
                    'mappedClient:id,name',
                    'mappedVendor:id,name',
                ])
                ->orderBy('ledger_name')
                ->get([
                    'id', 'ledger_name', 'group_name', 'parent_group', 'gstin_number',
                    'gst_type', 'state_name', 'country_name', 'opening_balance',
                    'opening_balance_type', 'credit_limit', 'is_active', 'last_synced_at',
                    'mapped_client_id', 'mapped_vendor_id',
                ]),
        ]);
    }

    public function stockItems(Tenant $tenant)
    {
        return inertia('Tally/StockItems', [
            'tenant' => $tenant,
            'items'  => TallyStockItem::where('tenant_id', $tenant->id)
                ->with(['mappedProduct:id,name'])
                ->orderBy('name')
                ->get([
                    'id', 'name', 'stock_group_name', 'category_name', 'unit_name',
                    'hsn_code', 'igst_rate', 'cgst_rate', 'sgst_rate',
                    'mrp_rate', 'standard_price', 'opening_balance', 'closing_balance',
                    'closing_value', 'is_active', 'last_synced_at', 'mapped_product_id',
                ]),
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
