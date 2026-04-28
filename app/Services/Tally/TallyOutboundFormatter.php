<?php

namespace App\Services\Tally;

use Illuminate\Support\Collection;

class TallyOutboundFormatter
{
    public function formatLedgerGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => $this->dropNulls([
            'AccobotId'      => $g->id,
            'TallyId'        => $g->tally_id,
            'AlterID'        => $g->alter_id,
            'Action'         => $g->action,
            'Name'           => $g->name,
            'UnderID'        => $g->under_id,
            'UnderName'      => $g->under_name,
            'NatureOfGroup'  => $g->nature_of_group,
        ]))->values()->all();
    }

    public function formatLedgers(Collection $ledgers): array
    {
        return $ledgers->map(fn ($l) => $this->nullToEmpty([
            'AccobotId'             => $l->id,
            'TallyId'               => $l->tally_id,
            'ERPID'                 => '',
            'AlterID'               => $l->alter_id,
            'LedgerName'            => $l->ledger_name,
            'Action'                => $l->action,
            'Group'                 => $l->group_name,
            'ParentGroup'           => $l->parent_group,
            'Description'           => $l->description,
            'Notes'                 => $l->notes,
            'CreditPeriod'          => $l->credit_period,
            'CreditLimit'           => $l->credit_limit,
            'InventoryAffected'     => $l->inventory_affected,
            'StateName'             => $l->state_name,
            'CountryName'           => $l->country_name,
            'PinCode'               => $l->pin_code,
            'Mobile_Number'         => $l->mobile_number,
            'ContactPerson'         => $l->contact_person,
            'ContactPerson_Email'   => $l->contact_person_email,
            'ContactPerson_EmailCC' => $l->contact_person_email_cc,
            'ContactPerson_Fax'     => $l->contact_person_fax,
            'ContactPerson_Website' => $l->contact_person_website,
            'ContactPerson_Mobile'  => $l->contact_person_mobile,
            'Opening_Balance'       => $l->opening_balance,
            'Opening_Balance_Type'  => $l->opening_balance_type,
            'GSTIN_Number'          => $l->gstin_number,
            'PAN_Number'            => $l->pan_number,
            'IsBillWiseOn'          => $this->boolStr($l->is_bill_wise_on),
            'MailingName'           => $l->mailing_name,
            'GST_Type'              => $l->gst_type,
            'LedgerAddress'         => $l->addresses ?? [],
            'BillAllocations'       => $l->bill_allocations ?? [],
            'Aliases'               => $l->aliases ?? [],
            'BankDetails'           => $l->bank_details ?? [],
        ]))->values()->all();
    }

    public function formatStockItems(Collection $items): array
    {
        return $items->map(fn ($s) => $this->dropNulls([
            'AccobotId'       => $s->id,
            'TallyId'         => $s->tally_id,
            'AlterID'         => $s->alter_id,
            'Action'          => $s->action,
            'Name'            => $s->name,
            'Description'     => $s->description,
            'Remarks'         => $s->remarks,
            'Aliases'         => $s->aliases ?? [],
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
        ]))->values()->all();
    }

    public function formatStockGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => $this->dropNulls([
            'AccobotId' => $g->id,
            'TallyId'   => $g->tally_id,
            'AlterID'   => $g->alter_id,
            'Action'    => $g->action,
            'Name'      => $g->name,
            'Parent'    => $g->parent,
            'Aliases'   => $g->aliases ?? [],
        ]))->values()->all();
    }

    public function formatStockCategories(Collection $cats): array
    {
        return $cats->map(fn ($c) => $this->dropNulls([
            'AccobotId' => $c->id,
            'TallyId'   => $c->tally_id,
            'AlterID'   => $c->alter_id,
            'Action'    => $c->action,
            'Name'      => $c->name,
            'Parent'    => $c->parent,
            'Aliases'   => $c->aliases ?? [],
        ]))->values()->all();
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

    public function formatGodowns(Collection $godowns): array
    {
        return $godowns->map(fn ($g) => $this->dropNulls([
            'AccobotId' => $g->id,
            'TallyId'   => $g->tally_id,
            'AlterID'   => $g->alter_id,
            'Action'    => $g->action,
            'Name'      => $g->name,
            'Under'     => $g->under,
            'Guid'      => $g->guid,
        ]))->values()->all();
    }

    public function formatUnits(Collection $units): array
    {
        return $units->map(fn ($u) => $this->dropNulls([
            'AccobotId'     => $u->id,
            'TallyId'       => $u->tally_id,
            'AlterID'       => $u->alter_id,
            'Action'        => $u->action,
            'Name'          => $u->name,
            'Symbol'        => $u->symbol,
            'FormalName'    => $u->formal_name,
            'DecimalPlaces' => $u->decimal_places,
            'UQC'           => $u->uqc,
        ]))->values()->all();
    }

    public function formatCompanyMasters(Collection $companies): array
    {
        return $companies->map(function ($c) {
            $flags    = $c->feature_flags ?? [];
            $deductor = $c->deductor_details ?? [];
            $legacy   = $c->legacy_tax_details ?? [];

            return $this->nullToEmpty(array_merge([
                'AccobotId'        => $c->id,
                'TallyId'          => $c->tally_id,
                'Action'           => $c->action ?? 'Create',
                'Guid'             => $c->company_guid,
                // Core identity
                'CompanyName'      => $c->company_name,
                'FormalName'       => $c->formal_name,
                'NameAlias'        => $c->name_alias,
                'Email'            => $c->email,
                'PhoneNumber'      => $c->phone_number,
                'FaxNumber'        => $c->fax_number,
                'Website'          => $c->website,
                'MobileNumbers'    => $c->mobile_numbers,
                'Address'          => $c->address,
                'Address1'         => $c->address1,
                'Address2'         => $c->address2,
                'Address3'         => $c->address3,
                'Address4'         => $c->address4,
                'Address5'         => $c->address5,
                'State'            => $c->state,
                'PriorState'       => $c->prior_state,
                'Country'          => $c->country,
                'CountryISDCode'   => $c->country_isd_code,
                'Pincode'          => $c->pincode,
                'BranchName'       => $c->branch_name,
                'BranchName2'      => $c->branch_name2,
                'Logopath'         => $c->logo_path,
                'PriceLevel'       => $c->price_level,
                'ConnectName'      => $c->connect_name,
                'DBName'           => $c->db_name,
                'CompanyNumber'    => $c->company_number,
                'StatutoryVersion' => $c->statutory_version,
                'CorporateIdentityNo' => $c->corporate_identity_no,
                'TallySerialNo'    => $c->tally_serial_no,
                'TallyLicenseType' => $c->licence_type,
                // Tax registration
                'IncomeTaxNumber'    => $c->income_tax_number,
                'SalesTaxNumber'     => $c->sales_tax_number,
                'InterstateSTNumber' => $c->interstate_st_number,
                'TANumber'           => $c->ta_number,
                // GST
                'GSTRegistrationNumber' => $c->gst_registration_number,
                'GSTRegistrationType'   => $c->gst_registration_type,
                'GSTApplicableDate'     => $c->gst_applicable_date,
                'GSTApplicability'      => $c->gst_applicability,
                'CmpTypeOfSupply'       => $c->cmp_type_of_supply,
                'HSNApplicability'      => $c->hsn_applicability,
                'eWayBillApplicableType'      => $c->eway_bill_applicable_type,
                'eWayBillInterStateThreshold' => $c->eway_bill_interstate_threshold,
                // Financial periods
                'StartingFrom'      => $c->starting_from,
                'BooksFrom'         => $c->books_from,
                'AuditedUpto'       => $c->audited_upto,
                'FIFOApplicableFrom' => $c->fifo_applicable_from,
                'ReturnsStartFrom'  => $c->returns_start_from,
                'ThisYearBeg'       => $c->this_year_beg,
                'ThisYearEnd'       => $c->this_year_end,
                'PrevYearBeg'       => $c->prev_year_beg,
                'PrevYearEnd'       => $c->prev_year_end,
                'ThisQuarterBeg'    => $c->this_quarter_beg,
                'ThisQuarterEnd'    => $c->this_quarter_end,
                'PrevQuarterBeg'    => $c->prev_quarter_beg,
                'PrevQuarterEnd'    => $c->prev_quarter_end,
            ], $flags, $deductor, $legacy));
        })->values()->all();
    }

    public function formatStatutoryMasters(Collection $items): array
    {
        return $items->map(fn ($s) => $this->dropNulls([
            'AccobotId'          => $s->id,
            'TallyId'            => $s->tally_id,
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
        ]))->values()->all();
    }

    public function formatEmployeeGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => $this->dropNulls([
            'AccobotId'          => $g->id,
            'TallyId'            => $g->tally_id,
            'AlterID'            => $g->alter_id,
            'Action'             => $g->action,
            'Name'               => $g->name,
            'Under'              => $g->under,
            'CostCentreCategory' => $g->cost_centre_category,
        ]))->values()->all();
    }

    public function formatEmployees(Collection $employees): array
    {
        return $employees->map(fn ($e) => $this->dropNulls([
            'AccobotId'      => $e->id,
            'TallyId'        => $e->tally_id,
            'AlterID'        => $e->alter_id,
            'Action'         => $e->action,
            'Name'           => $e->name,
            'EmployeeNumber' => $e->employee_number,
            'Parent'         => $e->parent,
            'Designation'    => $e->designation,
            'Function'       => $e->employee_function,
            'Location'       => $e->location,
            'JoiningDate'    => $e->date_of_joining?->toDateString(),
            'ResignationDate' => $e->date_of_leaving?->toDateString(),
            'DOB'            => $e->date_of_birth?->toDateString(),
            'Gender'         => $e->gender,
            'FatherName'     => $e->father_name,
            'SpouseName'     => $e->spouse_name,
            'Aliases'        => $e->aliases ? array_map(fn($a) => ['Alias' => $a], $e->aliases) : [],
        ]))->values()->all();
    }

    public function formatPayHeads(Collection $payHeads): array
    {
        return $payHeads->map(fn ($p) => $this->dropNulls([
            'AccobotId'         => $p->id,
            'TallyId'           => $p->tally_id,
            'AlterID'           => $p->alter_id,
            'Action'            => $p->action,
            'Name'              => $p->name,
            'PayType'           => $p->pay_type,
            'IncomeType'        => $p->income_type,
            'ParentGroup'       => $p->parent_group,
            'CalculationType'   => $p->calculation_type,
            'LeaveType'         => $p->leave_type,
            'CalculationPeriod' => $p->calculation_period,
        ]))->values()->all();
    }

    public function formatAttendanceTypes(Collection $types): array
    {
        return $types->map(fn ($t) => $this->dropNulls([
            'AccobotId'      => $t->id,
            'TallyId'        => $t->tally_id,
            'AlterID'        => $t->alter_id,
            'Action'         => $t->action,
            'Name'           => $t->name,
            'AttendanceType' => $t->attendance_type,
            'UnitOfMeasure'  => $t->attendance_period,
        ]))->values()->all();
    }

    public function formatSalaryVouchers(Collection $vouchers): array
    {
        return $vouchers->map(fn ($v) => $this->dropNulls([
            'AccobotId'     => $v->id,
            'TallyId'       => $v->tally_id,
            'AlterID'       => $v->alter_id,
            'Action'        => $v->action,
            'VoucherType'   => $v->voucher_type,
            'VoucherNumber' => $v->voucher_number,
            'VoucherDate'   => $v->voucher_date?->format('Y-m-d'),
            'Narration'     => $v->narration,
            'EmployeeAllocations' => $v->employeeAllocations->map(fn ($a) => $this->dropNulls([
                'EmployeeName'   => $a->employee_name,
                'EmployeeGroup'  => $a->employee_group,
                'PayHeadEntries' => $a->entries ?? [],
                'NetPayable'     => $a->net_payable,
            ]))->values()->all(),
        ]))->values()->all();
    }

    public function formatAttendanceVouchers(Collection $vouchers): array
    {
        return $vouchers->map(fn ($v) => $this->dropNulls([
            'AccobotId'     => $v->id,
            'TallyId'       => $v->tally_id,
            'AlterID'       => $v->alter_id,
            'Action'        => $v->action,
            'VoucherType'   => $v->voucher_type,
            'VoucherNumber' => $v->voucher_number,
            'VoucherDate'   => $v->voucher_date?->format('Y-m-d'),
            'Narration'     => $v->narration,
            'EmployeeAllocations' => $v->employeeAllocations->map(fn ($a) => $this->dropNulls([
                'EmployeeName'      => $a->employee_name,
                'EmployeeGroup'     => $a->employee_group,
                'AttendanceEntries' => $a->entries ?? [],
            ]))->values()->all(),
        ]))->values()->all();
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function formatVouchers(Collection $vouchers): array
    {
        return $vouchers->map(function ($v) {
            $base = $this->dropNulls([
                'AccobotId'     => $v->id,
                'TallyId'       => $v->tally_id,
                'AlterID'       => $v->alter_id,
                'Action'        => $v->action,
                'VoucherType'   => $v->voucher_type,
                'VoucherNumber' => $v->voucher_number,
                'VoucherDate'   => $v->voucher_date?->format('Y-m-d'),
                'Reference'     => $v->reference,
                'ReferenceDate' => $v->reference_date,
                'PartyName'     => $v->party_name,
                'VoucherTotal'  => $v->voucher_total,
                'IsInvoice'     => $this->boolStr($v->is_invoice),
                'PlaceOfSupply' => $v->place_of_supply,
                'VoucherCostCentre' => $v->cost_centre,
                'IsDeleted'         => $this->boolStr($v->is_deleted),

                // Dispatch / shipping
                'DeliveryNoteNo'   => $v->delivery_note_no,
                'DeliveryNoteDate' => $v->delivery_note_date,
                'DispatchDocNo'    => $v->dispatch_doc_no,
                'DispatchThrough'  => $v->dispatch_through,
                'Destination'      => $v->destination,
                'CarrierName'      => $v->carrier_name,
                'LRNo'             => $v->lr_no,
                'LRDate'           => $v->lr_date,
                'MotorVehicleNo'   => $v->motor_vehicle_no,

                // Order
                'OrderNo'          => $v->order_no,
                'OrderDate'        => $v->order_date,
                'TermsOfPayment'   => $v->terms_of_payment,
                'OtherReferences'  => $v->other_references,
                'TermsOfDelivery'  => $v->terms_of_delivery,

                // Buyer
                'BuyerName'                => $v->buyer_name,
                'BuyerAlias'               => $v->buyer_alias,
                'BuyerGSTIN'               => $v->buyer_gstin,
                'BuyerPinCode'             => $v->buyer_pin_code,
                'BuyerState'               => $v->buyer_state,
                'BuyerCountryName'         => $v->buyer_country,
                'BuyerGSTRegistrationType' => $v->buyer_gst_registration_type,
                'BuyerEmail'               => $v->buyer_email,
                'BuyerMobile'              => $v->buyer_mobile,
                'BuyerAddress' => is_array($v->buyer_address)
                    ? $v->buyer_address
                    : ($v->buyer_address ? [['BuyerAddress' => $v->buyer_address]] : []),

                // Consignee
                'ConsigneeName'                => $v->consignee_name,
                'ConsigneeGSTIN'               => $v->consignee_gstin,
                'ConsigneeTallyGroup'          => $v->consignee_tally_group,
                'ConsigneePinCode'             => $v->consignee_pin_code,
                'ConsigneeState'               => $v->consignee_state,
                'ConsigneeCountryName'         => $v->consignee_country,
                'ConsigneeGSTRegistrationType' => $v->consignee_gst_registration_type,

                'Narration'           => $v->narration,
                'IRN'                 => $v->irn,
                'AcknowledgementNo'   => $v->acknowledgement_no,
                'AcknowledgementDate' => $v->acknowledgement_date,
                'QRCode'              => $v->qr_code,
            ]);

            $base['InventoryEntries'] = $v->inventoryEntries->map(fn ($ie) => $this->dropNulls([
                'StockItemName'    => $ie->stock_item_name,
                'ItemCode'         => $ie->item_code,
                'GroupName'        => $ie->group_name,
                'HSNCode'          => $ie->hsn_code,
                'Unit'             => $ie->unit,
                'IGSTRate'         => $ie->igst_rate,
                'CessRate'         => $ie->cess_rate,
                'IsDeemedPositive' => $this->boolStr($ie->is_deemed_positive),
                'ActualQty'        => $ie->actual_qty,
                'BilledQty'        => $ie->billed_qty,
                'Rate'             => $ie->rate,
                'DiscountPercent'  => $ie->discount_percent,
                'Amount'           => $ie->amount,
                'TaxAmount'        => $ie->tax_amount,
                'MRP'              => $ie->mrp,
                'SalesLedger'           => $ie->sales_ledger,
                'GodownName'            => $ie->godown_name,
                'BatchName'             => $ie->batch_name,
                'BatchAllocations'      => $ie->batch_allocations ?? [],
                'AccountingAllocations' => $ie->accounting_allocations ?? [],
            ]))->values()->all();

            $base['LedgerEntries'] = $v->ledgerEntries->map(fn ($le) => $this->dropNulls([
                'LedgerName'            => $le->ledger_name,
                'LedgerGroup'           => $le->ledger_group,
                'LedgerAmount'          => $le->ledger_amount,
                'IsDeemedPositive'      => $this->boolStr($le->is_deemed_positive),
                'IsPartyLedger'         => $this->boolStr($le->is_party_ledger),
                'IGSTRate'              => $le->igst_rate,
                'HSNCode'               => $le->hsn_code,
                'CessRate'              => $le->cess_rate,
                'BillsAllocation'       => $le->bills_allocation ?? [],
                'BankAllocationDetails' => $le->bank_allocation_details ?? [],
            ]))->values()->all();

            return $base;
        })->values()->all();
    }

    private function dropNulls(array $record): array
    {
        return array_filter($record, fn($v) => !is_null($v));
    }

    private function nullToEmpty(array $record): array
    {
        return array_map(fn($v) => is_null($v) ? '' : $v, $record);
    }

    private function boolStr(?bool $v): string
    {
        if (is_null($v)) return '';
        return $v ? 'Yes' : 'No';
    }
}
