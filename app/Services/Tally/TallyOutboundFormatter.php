<?php

namespace App\Services\Tally;

use Illuminate\Support\Collection;

class TallyOutboundFormatter
{
    public function formatLedgerGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => [
            'AccobotId'      => $g->id,
            'ID'             => $g->tally_id,
            'AlterID'        => $g->alter_id,
            'Action'         => $g->action,
            'Name'           => $g->name,
            'UnderID'        => $g->under_id,
            'UnderName'      => $g->under_name,
            'NatureOfGroup'  => $g->nature_of_group,
        ])->values()->all();
    }

    public function formatLedgers(Collection $ledgers): array
    {
        return $ledgers->map(fn ($l) => [
            'AccobotId'                => $l->id,
            'ID'                       => $l->tally_id,
            'AlterID'                  => $l->alter_id,
            'Action'                   => $l->action,
            'LedgerName'               => $l->ledger_name,
            'GroupName'                => $l->group_name,
            'ParentGroup'              => $l->parent_group,
            'IsBillWiseOn'      => $this->boolStr($l->is_bill_wise_on),
            'InventoryAffected' => $this->boolStr($l->inventory_affected),
            'GSTINNumber'       => $l->gstin_number,
            'PANNumber'         => $l->pan_number,
            'GSTType'           => $l->gst_type,
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
            'OpeningBalance'     => $l->opening_balance,
            'OpeningBalanceType' => $l->opening_balance_type,
            'Aliases'            => $l->aliases ?? [],
            'Description'              => $l->description,
            'Notes'                    => $l->notes,
            'BankDetails'              => $l->bank_details ?? [],
        ])->values()->all();
    }

    public function formatStockItems(Collection $items): array
    {
        return $items->map(fn ($s) => [
            'AccobotId'              => $s->id,
            'ID'                     => $s->tally_id,
            'AlterID'                => $s->alter_id,
            'Action'                 => $s->action,
            'Name'                   => $s->name,
            'Description'            => $s->description,
            'Remarks'                => $s->remarks,
            'Aliases'                => $s->aliases ?? [],
            'StockGroupID'    => $s->stock_group_id,
            'StockGroupName'  => $s->stock_group_name,
            'StockCategoryID' => $s->stock_category_id,
            'Category'        => $s->category_name,
            'UnitID'          => $s->unit_id,
            'Unit'            => $s->unit_name,
            'AlternateUnit'   => $s->alternate_unit,
            'Conversion'      => $s->conversion,
            'Denominator'     => $s->denominator,
            'IsGSTApplicable' => $this->boolStr($s->is_gst_applicable),
            'Taxablity'       => $s->taxability,
            'CalculationType' => $s->calculation_type,
            'IGST_Rate'       => $s->igst_rate,
            'SGST_Rate'       => $s->sgst_rate,
            'CGST_Rate'       => $s->cgst_rate,
            'CESS_Rate'       => $s->cess_rate,
            'HSNCode'         => $s->hsn_code,
            'MRPRate'         => $s->mrp_rate,
            'Opening_Balance' => $s->opening_balance,
            'Opening_Rate'    => $s->opening_rate,
            'Opening_Value'   => $s->opening_value,
            'Closing_Balance' => $s->closing_balance,
            'Closing_Rate'    => $s->closing_rate,
            'Closing_Value'   => $s->closing_value,
        ])->values()->all();
    }

    public function formatStockGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => [
            'AccobotId'           => $g->id,
            'ID'                  => $g->tally_id,
            'AlterID'             => $g->alter_id,
            'Action'              => $g->action,
            'Name'                => $g->name,
            'Parent'  => $g->parent,
            'Aliases' => $g->aliases ?? [],
        ])->values()->all();
    }

    public function formatStockCategories(Collection $cats): array
    {
        return $cats->map(fn ($c) => [
            'AccobotId'  => $c->id,
            'ID'         => $c->tally_id,
            'AlterID'    => $c->alter_id,
            'Action'     => $c->action,
            'Name'    => $c->name,
            'Parent'  => $c->parent,
            'Aliases' => $c->aliases ?? [],
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

    public function formatReceiptVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatPaymentVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatContraVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatJournalVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatStatutoryMasters(Collection $items): array
    {
        return $items->map(fn ($s) => [
            'AccobotId'          => $s->id,
            'ID'                 => $s->tally_id,
            'AlterID'            => $s->alter_id,
            'Action'             => $s->action,
            'Name'               => $s->name,
            'StatutoryType'      => $s->statutory_type,
            'RegistrationNumber' => $s->registration_number,
            'StateCode'          => $s->state_code,
            'RegistrationType'   => $s->registration_type,
            'PAN'                => $s->pan,
            'TAN'                => $s->tan,
            'ApplicableFrom'     => $s->applicable_from?->toDateString(),
            'Details'            => $s->details ?? [],
        ])->values()->all();
    }

    public function formatEmployeeGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => [
            'AccobotId'  => $g->id,
            'ID'         => $g->tally_id,
            'AlterID'    => $g->alter_id,
            'Action'     => $g->action,
            'Name'                => $g->name,
            'Under'               => $g->under,
            'CostCentreCategory'  => $g->cost_centre_category,
        ])->values()->all();
    }

    public function formatEmployees(Collection $employees): array
    {
        return $employees->map(fn ($e) => [
            'AccobotId'         => $e->id,
            'ID'                => $e->tally_id,
            'AlterID'           => $e->alter_id,
            'Action'            => $e->action,
            'Name'             => $e->name,
            'EmployeeNumber'   => $e->employee_number,
            'Parent'           => $e->parent,
            'Designation'      => $e->designation,
            'Function'         => $e->employee_function,
            'Location'         => $e->location,
            'JoiningDate'      => $e->date_of_joining?->toDateString(),
            'ResignationDate'  => $e->date_of_leaving?->toDateString(),
            'DOB'              => $e->date_of_birth?->toDateString(),
            'Gender'           => $e->gender,
            'FatherName'       => $e->father_name,
            'SpouseName'       => $e->spouse_name,
            'Aliases'          => $e->aliases ? array_map(fn($a) => ['Alias' => $a], $e->aliases) : [],
        ])->values()->all();
    }

    public function formatPayHeads(Collection $payHeads): array
    {
        return $payHeads->map(fn ($p) => [
            'AccobotId'       => $p->id,
            'ID'              => $p->tally_id,
            'AlterID'         => $p->alter_id,
            'Action'          => $p->action,
            'Name'               => $p->name,
            'PayType'            => $p->pay_type,
            'IncomeType'         => $p->income_type,
            'ParentGroup'        => $p->parent_group,
            'CalculationType'    => $p->calculation_type,
            'LeaveType'          => $p->leave_type,
            'CalculationPeriod'  => $p->calculation_period,
        ])->values()->all();
    }

    public function formatAttendanceTypes(Collection $types): array
    {
        return $types->map(fn ($t) => [
            'AccobotId'      => $t->id,
            'ID'             => $t->tally_id,
            'AlterID'        => $t->alter_id,
            'Action'         => $t->action,
            'Name'           => $t->name,
            'AttendanceType' => $t->attendance_type,
            'UnitOfMeasure'  => $t->attendance_period,
        ])->values()->all();
    }

    public function formatSalaryVouchers(Collection $vouchers): array
    {
        return $vouchers->map(fn ($v) => [
            'AccobotId'     => $v->id,
            'MasterID'      => $v->tally_id,
            'AlterID'       => $v->alter_id,
            'Action'        => $v->action,
            'VoucherType'   => $v->voucher_type,
            'VoucherNumber' => $v->voucher_number,
            'VoucherDate'   => $v->voucher_date?->format('Y-m-d'),
            'Narration'     => $v->narration,
            'EmployeeAllocations' => $v->employeeAllocations->map(fn ($a) => [
                'EmployeeName'  => $a->employee_name,
                'EmployeeGroup' => $a->employee_group,
                'PayHeadEntries' => $a->entries ?? [],
                'NetPayable'    => $a->net_payable,
            ])->values()->all(),
        ])->values()->all();
    }

    public function formatAttendanceVouchers(Collection $vouchers): array
    {
        return $vouchers->map(fn ($v) => [
            'AccobotId'     => $v->id,
            'MasterID'      => $v->tally_id,
            'AlterID'       => $v->alter_id,
            'Action'        => $v->action,
            'VoucherType'   => $v->voucher_type,
            'VoucherNumber' => $v->voucher_number,
            'VoucherDate'   => $v->voucher_date?->format('Y-m-d'),
            'Narration'     => $v->narration,
            'EmployeeAllocations' => $v->employeeAllocations->map(fn ($a) => [
                'EmployeeName'     => $a->employee_name,
                'EmployeeGroup'    => $a->employee_group,
                'AttendanceEntries' => $a->entries ?? [],
            ])->values()->all(),
        ])->values()->all();
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function formatVouchers(Collection $vouchers): array
    {
        return $vouchers->map(function ($v) {
            $base = [
                'AccobotId'     => $v->id,
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
                'BuyerEmail'    => $v->buyer_email,
                'BuyerMobile'   => $v->buyer_mobile,
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
                'LedgerName'       => $le->ledger_name,
                'LedgerGroup'      => $le->ledger_group,
                'LedgerAmount'     => $le->ledger_amount,
                'IsDeemedPositive' => $this->boolStr($le->is_deemed_positive),
                'IsPartyLedger'    => $this->boolStr($le->is_party_ledger),
                'BillsAllocation'  => $le->bills_allocation ?? [],
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
