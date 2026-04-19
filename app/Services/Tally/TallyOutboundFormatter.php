<?php

namespace App\Services\Tally;

use Illuminate\Support\Collection;

class TallyOutboundFormatter
{
    public function formatLedgerGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => [
            'ID'             => $g->tally_id,
            'AlterID'        => $g->alter_id,
            'Action'         => $g->action,
            'Name'           => $g->name,
            'UnderID'        => $g->under_id,
            'UnderName'      => $g->under_name,
            'NatureOfGroup'  => $g->nature_of_group,
            'IsRevenue'      => $this->boolStr($g->is_revenue),
            'AffectsGross'   => $this->boolStr($g->affects_gross),
            'IsAddable'      => $this->boolStr($g->is_addable),
        ])->values()->all();
    }

    public function formatLedgers(Collection $ledgers): array
    {
        return $ledgers->map(fn ($l) => [
            'ID'                       => $l->tally_id,
            'AlterID'                  => $l->alter_id,
            'Action'                   => $l->action,
            'LedgerName'               => $l->ledger_name,
            'GroupName'                => $l->group_name,
            'ParentGroup'              => $l->parent_group,
            'IsBillWiseOn'             => $this->boolStr($l->is_bill_wise_on),
            'InventoryAffected'        => $this->boolStr($l->inventory_affected),
            'IsCostCentreApplicable'   => $this->boolStr($l->is_cost_centre_applicable),
            'GSTINNumber'              => $l->gstin_number,
            'PANNumber'                => $l->pan_number,
            'TANNumber'                => $l->tan_number,
            'GSTType'                  => $l->gst_type,
            'IsRCMApplicable'          => $this->boolStr($l->is_rcm_applicable),
            'MailingName'              => $l->mailing_name,
            'MobileNumber'             => $l->mobile_number,
            'ContactPerson'            => $l->contact_person,
            'ContactPersonEmail'       => $l->contact_person_email,
            'ContactPersonMobile'      => $l->contact_person_mobile,
            'Addresses'                => $l->addresses ?? [],
            'StateName'                => $l->state_name,
            'CountryName'              => $l->country_name,
            'PinCode'                  => $l->pin_code,
            'CreditPeriod'             => $l->credit_period,
            'CreditLimit'              => $l->credit_limit,
            'OpeningBalance'           => $l->opening_balance,
            'OpeningBalanceType'       => $l->opening_balance_type,
            'BankDetails'              => $l->bank_details ?? [],
            'Aliases'                  => $l->aliases ?? [],
            'Description'              => $l->description,
            'Notes'                    => $l->notes,
        ])->values()->all();
    }

    public function formatStockItems(Collection $items): array
    {
        return $items->map(fn ($s) => [
            'ID'                     => $s->tally_id,
            'AlterID'                => $s->alter_id,
            'Action'                 => $s->action,
            'Name'                   => $s->name,
            'Description'            => $s->description,
            'Remarks'                => $s->remarks,
            'Aliases'                => $s->aliases ?? [],
            'StockGroupID'           => $s->stock_group_id,
            'StockGroupName'         => $s->stock_group_name,
            'StockCategoryID'        => $s->stock_category_id,
            'CategoryName'           => $s->category_name,
            'UnitID'                 => $s->unit_id,
            'UnitName'               => $s->unit_name,
            'AlternateUnit'          => $s->alternate_unit,
            'Conversion'             => $s->conversion,
            'Denominator'            => $s->denominator,
            'IsGSTApplicable'        => $this->boolStr($s->is_gst_applicable),
            'Taxability'             => $s->taxability,
            'CalculationType'        => $s->calculation_type,
            'IGSTRate'               => $s->igst_rate,
            'SGSTRate'               => $s->sgst_rate,
            'CGSTRate'               => $s->cgst_rate,
            'CessRate'               => $s->cess_rate,
            'HSNCode'                => $s->hsn_code,
            'MRPRate'                => $s->mrp_rate,
            'StandardCost'           => $s->standard_cost,
            'StandardPrice'          => $s->standard_price,
            'OpeningBalance'         => $s->opening_balance,
            'OpeningRate'            => $s->opening_rate,
            'OpeningValue'           => $s->opening_value,
            'ClosingBalance'         => $s->closing_balance,
            'ClosingRate'            => $s->closing_rate,
            'ClosingValue'           => $s->closing_value,
            'CostingMethod'          => $s->costing_method,
            'IsBatchApplicable'      => $this->boolStr($s->is_batch_applicable),
            'IsExpiryDateApplicable' => $this->boolStr($s->is_expiry_date_applicable),
            'ReorderLevel'           => $s->reorder_level,
            'ReorderQuantity'        => $s->reorder_quantity,
            'MaximumQuantity'        => $s->maximum_quantity,
            'BatchAllocations'       => $s->batch_allocations ?? [],
        ])->values()->all();
    }

    public function formatStockGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => [
            'ID'                  => $g->tally_id,
            'AlterID'             => $g->alter_id,
            'Action'              => $g->action,
            'Name'                => $g->name,
            'ParentID'            => $g->parent_id,
            'ParentName'          => $g->parent_name,
            'NatureOfGroup'       => $g->nature_of_group,
            'ShouldAddQuantities' => $this->boolStr($g->should_add_quantities),
        ])->values()->all();
    }

    public function formatStockCategories(Collection $cats): array
    {
        return $cats->map(fn ($c) => [
            'ID'         => $c->tally_id,
            'AlterID'    => $c->alter_id,
            'Action'     => $c->action,
            'Name'       => $c->name,
            'ParentName' => $c->parent_name,
        ])->values()->all();
    }

    public function formatSalesVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatPurchaseVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatDebitNoteVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatCreditNoteVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function formatVouchers(Collection $vouchers): array
    {
        return $vouchers->map(function ($v) {
            $base = [
                'MasterID'      => $v->tally_id,
                'AlterID'       => $v->alter_id,
                'Action'        => $v->action,
                'VoucherType'   => $v->voucher_type,
                'VoucherNumber' => $v->voucher_number,
                'VoucherDate'   => $v->voucher_date?->format('Y-m-d'),
                'Reference'     => $v->reference,
                'PartyName'     => $v->party_name,
                'VoucherTotal'  => $v->voucher_total,
                'IsInvoice'     => $this->boolStr($v->is_invoice),
                'PlaceOfSupply' => $v->place_of_supply,
                'BuyerName'     => $v->buyer_name,
                'BuyerGSTIN'    => $v->buyer_gstin,
                'BuyerState'    => $v->buyer_state,
                'BuyerAddress'  => $v->buyer_address,
                'Narration'     => $v->narration,
                'IRN'           => $v->irn,
                'AcknowledgementNo'   => $v->acknowledgement_no,
                'AcknowledgementDate' => $v->acknowledgement_date,
                'QRCode'        => $v->qr_code,
            ];

            $base['InventoryEntries'] = $v->inventoryEntries->map(fn ($ie) => [
                'StockItemName'    => $ie->stock_item_name,
                'HSNCode'          => $ie->hsn_code,
                'Unit'             => $ie->unit,
                'IGSTRate'         => $ie->igst_rate,
                'ActualQty'        => $ie->actual_qty,
                'BilledQty'        => $ie->billed_qty,
                'Rate'             => $ie->rate,
                'DiscountPercent'  => $ie->discount_percent,
                'Amount'           => $ie->amount,
                'TaxAmount'        => $ie->tax_amount,
            ])->values()->all();

            $base['LedgerEntries'] = $v->ledgerEntries->map(fn ($le) => [
                'LedgerName'      => $le->ledger_name,
                'LedgerGroup'     => $le->ledger_group,
                'LedgerAmount'    => $le->ledger_amount,
                'IsDeemedPositive'=> $this->boolStr($le->is_deemed_positive),
                'IsPartyLedger'   => $this->boolStr($le->is_party_ledger),
            ])->values()->all();

            return $base;
        })->values()->all();
    }

    private function boolStr(?bool $v): string
    {
        if (is_null($v)) return '';
        return $v ? 'Yes' : 'No';
    }
}
