<?php

namespace App\Services\Tally;

use Illuminate\Support\Collection;

class TallyOutboundFormatter
{
    public function formatLedgerGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => $this->dropNulls([
            'AccobotId'           => $g->id,
            'TallyId'             => $g->tally_id,
            'ERPID'               => $g->erp_id ?? '',
            'AlterID'             => $g->alter_id,
            'Action'              => $g->action,
            'Name'                => $g->name,
            'UnderID'             => $g->under_id,
            'UnderName'           => $g->under_name,
            'NatureOfGroup'       => $g->nature_of_group,
            'IsSubLedger'         => $this->boolStr($g->is_sub_ledger),
            'IsDeemedPositive'    => $this->boolStr($g->is_deemed_positive),
            'UsedForCalculation'  => $this->boolStr($g->used_for_calculation),
            'MethodToAllocate'    => $g->method_to_allocate,
            'IsAddable'           => $this->boolStr($g->is_addable),
            'TDSCategoryDetails'  => $g->tds_category_details ?? [],
        ]))->values()->all();
    }

    public function formatLedgers(Collection $ledgers): array
    {
        return $ledgers->map(fn ($l) => $this->nullToEmpty([
            'AccobotId'             => $l->id,
            'TallyId'               => $l->tally_id,
            'Guid'                  => $l->guid,
            'AlterID'               => $l->alter_id,
            'LedgerName'            => $l->ledger_name,
            'Action'                => $l->action,
            'Group'                 => $l->group_name,
            'ParentGroup'           => $l->parent_group,
            'Description'           => $l->description,
            'Notes'                 => $l->notes,
            'CurrencyName'          => $l->currency_name,
            'MailingName'           => $l->mailing_name,
            // Credit
            'CreditPeriod'          => $l->credit_period,
            'CreditLimit'           => $l->credit_limit,
            'IsCreditDaysCheckOn'   => $this->boolStr($l->is_credit_days_check_on),
            'OverrideCreditLimit'   => $this->boolStr($l->override_credit_limit),
            // Behaviour
            'InventoryAffected'     => $l->inventory_affected,
            'IsBillWiseOn'          => $this->boolStr($l->is_bill_wise_on),
            'IsCostCentresOn'       => $this->boolStr($l->is_cost_centres_on),
            'IsCostTrackingOn'      => $this->boolStr($l->is_cost_tracking_on),
            // Address
            'StateName'             => $l->state_name,
            'CountryName'           => $l->country_name,
            'PinCode'               => $l->pin_code,
            'LedgerCountryISDCode'  => $l->ledger_country_isd_code,
            // Contact
            'MobileNumber'          => $l->mobile_number,
            'ContactPerson'         => $l->contact_person,
            'ContactPersonEmail'    => $l->contact_person_email,
            'ContactPersonEmailCC'  => $l->contact_person_email_cc,
            'ContactPersonFax'      => $l->contact_person_fax,
            'ContactPersonWebsite'  => $l->contact_person_website,
            'ContactPersonMobile'   => $l->contact_person_mobile,
            // Opening Balance
            'OpeningBalance'        => $l->opening_balance,
            'OpeningBalanceType'    => $l->opening_balance_type,
            // GST
            'GSTINNumber'           => $l->gstin_number,
            'GSTTypeLedger'         => $l->gst_type_ledger,
            'GSTRegistrationType'   => $l->gst_registration_type,
            'GSTApplicableFrom'     => $l->gst_applicable_from,
            'IsGSTApplicable'       => $this->boolStr($l->is_gst_applicable),
            'IsSEZParty'            => $this->boolStr($l->is_sez_party),
            'IsTransporter'         => $this->boolStr($l->is_transporter),
            'IsCommonParty'         => $this->boolStr($l->is_common_party),
            'IsOtherTerritoryAssessee' => $this->boolStr($l->is_other_territory_assessee),
            'IsRCMApplicable'       => $this->boolStr($l->is_rcm_applicable),
            'AppropriateFor'        => $l->appropriate_for,
            // PAN
            'PANNumber'             => $l->pan_number,
            'PANApplicableFrom'     => $l->pan_applicable_from,
            'NameOnPAN'             => $l->name_on_pan,
            // TDS / TCS
            'IsTDSApplicable'       => $this->boolStr($l->is_tds_applicable),
            'TDSDeducteeType'       => $l->tds_deductee_type,
            'IsTDSProjected'        => $this->boolStr($l->is_tds_projected),
            'IsTCSApplicable'       => $this->boolStr($l->is_tcs_applicable),
            // Legacy tax
            'VATTINNumber'          => $l->vat_tin_number,
            'TaxType'               => $l->tax_type,
            'UsedForTaxType'        => $l->used_for_tax_type,
            'RegistrationNumber'    => $l->registration_number,
            'ServiceCategory'       => $l->service_category,
            'ImporterExporterCode'  => $l->importer_exporter_code,
            // Interest
            'TypeOfInterestOn'              => $l->type_of_interest_on,
            'IsInterestOn'                  => $this->boolStr($l->is_interest_on),
            'InterestOnBillWise'            => $this->boolStr($l->is_interest_on_bill_wise),
            'OverrideInterest'              => $this->boolStr($l->override_interest),
            'OverrideAdvInterest'           => $this->boolStr($l->override_adv_interest),
            'IsInterestInclLastDay'         => $this->boolStr($l->is_interest_incl_last_day),
            'InterestInclDayOfAddition'     => $this->boolStr($l->interest_incl_day_of_addition),
            'InterestInclDayOfDeduction'    => $this->boolStr($l->interest_incl_day_of_deduction),
            // Bank flat fields
            'BankAccountHolderName' => $l->bank_account_holder_name,
            'SwiftCode'             => $l->swift_code,
            'BranchName'            => $l->branch_name,
            'BankBSRCode'           => $l->bank_bsr_code,
            'DefaultTransferMode'   => $l->default_transfer_mode,
            'IsChequePrintingEnabled' => $this->boolStr($l->is_cheque_printing_enabled),
            // Party / payroll flags
            'IsRelatedParty'        => $this->boolStr($l->is_related_party),
            'ForPayroll'            => $this->boolStr($l->for_payroll),
            'UseForESIEligibility'  => $this->boolStr($l->use_for_esi_eligibility),
            'IsECashLedger'         => $l->is_e_cash_ledger,
            'IgnoreMismatchWithWarning' => $this->boolStr($l->ignore_mismatch_with_warning),
            'IgnoreTDSExempt'       => $this->boolStr($l->ignore_tds_exempt),
            'ITExemptApplicable'    => $l->it_exempt_applicable,
            // JSONB arrays
            'LedgerAddress'             => $l->addresses ?? [],
            'Aliases'                   => $l->aliases ?? [],
            'BankDetails'               => $l->bank_details ?? [],
            'BillAllocations'           => $l->bill_allocations ?? [],
            'GSTRegistrationDetails'    => $l->gst_registration_details ?? [],
            'ChequeRanges'              => $l->cheque_ranges ?? [],
            'TransferModeLimits'        => $l->transfer_mode_limits ?? [],
            'InterestCollection'        => $l->interest_collection ?? [],
            'TDSCategoryDetails'        => $l->tds_category_details ?? [],
            'TCSCategoryDetails'        => $l->tcs_category_details ?? [],
            'ContactDetails'            => $l->contact_details ?? [],
            'DeductInSameVchRules'      => $l->deduct_in_same_vch_rules ?? [],
            'LedMultiAddressList'       => $l->led_multi_address_list ?? [],
        ]))->values()->all();
    }

    public function formatStockItems(Collection $items): array
    {
        return $items->map(fn ($s) => $this->dropNulls([
            'AccobotId'          => $s->id,
            'TallyId'            => $s->tally_id,
            'Guid'               => $s->guid ?? '',
            'AlterID'            => $s->alter_id,
            'Action'             => $s->action,
            // Identity
            'Name'               => $s->name,
            'Description'        => $s->description     ?? '',
            'Remarks'            => $s->remarks          ?? '',
            // Classification
            'StockCategoryID'    => (int) ($s->stock_category_id ?? 0),
            'Category'           => $s->category_name    ?? '',
            'StockGroupID'       => (int) ($s->stock_group_id    ?? 0),
            'StockGroupName'     => $s->stock_group_name ?? '',
            // Units
            'UnitID'             => (int) ($s->unit_id   ?? 0),
            'Unit'               => $s->unit_name        ?? '',
            'AlternateUnit'      => $s->alternate_unit   ?? '',
            'Conversion'         => (float) ($s->conversion ?? 0),
            'Denominator'        => (int) $s->denominator,
            'ReportingUOM'       => $s->reporting_uom    ?? '',
            'ReportingUOMDate'   => $s->reporting_uom_date ?? '',
            // GST & Tax
            'IsGSTApplicable'    => $s->is_gst_applicable ? 'Applicable' : 'Not Applicable',
            'CalculationType'    => $s->calculation_type ?? '',
            'Taxablity'          => $s->taxability       ?? '',
            'IGST_Rate'          => (float) $s->igst_rate,
            'SGST_Rate'          => (float) $s->sgst_rate,
            'CGST_Rate'          => (float) $s->cgst_rate,
            'CESS_Rate'          => (float) $s->cess_rate,
            'HSNCode'            => $s->hsn_code         ?? '',
            'HSNDesc'            => $s->hsn_desc         ?? '',
            'TypeOfSupply'       => $s->type_of_supply   ?? '',
            // TCS
            'TCSApplicable'      => $s->tcs_applicable   ?? '',
            'TCSCategory'        => $s->tcs_category     ?? '',
            // Pricing
            'MRPRate'            => (float) ($s->mrp_rate ?? 0),
            'InclusiveTax'       => $this->boolStr($s->inclusive_tax),
            'ModifyMRPRate'      => $this->boolStr($s->modify_mrp_rate),
            'CalcOnMRP'          => $this->boolStr($s->calc_on_mrp),
            'MRPInclOfTax'       => $this->boolStr($s->mrp_incl_of_tax),
            'BasicRateOfExcise'  => (float) ($s->basic_rate_of_excise ?? 0),
            // Costing / Valuation
            'CostingMethod'      => $s->costing_method   ?? '',
            'ValuationMethod'    => $s->valuation_method ?? '',
            // Default Ledgers
            'SalesLedger'        => $s->sales_ledger     ?? '',
            'SalesLedgerRate'    => (float) ($s->sales_ledger_rate ?? 0),
            'PurchaseLedger'     => $s->purchase_ledger  ?? '',
            'PurchaseLedgerRate' => (float) ($s->purchase_ledger_rate ?? 0),
            // Stock Levels
            'Opening_Balance'    => (float) $s->opening_balance,
            'Opening_Rate'       => (float) $s->opening_rate,
            'Opening_Value'      => (float) $s->opening_value,
            'Closing_Balance'    => (float) $s->closing_balance,
            'Closing_Rate'       => (float) $s->closing_rate,
            'Closing_Value'      => (float) $s->closing_value,
            // Inventory Behaviour
            'IsBatchWise'        => $this->boolStr($s->is_batch_wise),
            'IsPerishable'       => $this->boolStr($s->is_perishable),
            'HasMfgDate'         => $this->boolStr($s->has_mfg_date),
            'AllowExpiredItems'  => $this->boolStr($s->allow_expired_items),
            'IgnoreBatches'      => $this->boolStr($s->ignore_batches),
            'IgnoreGodowns'      => $this->boolStr($s->ignore_godowns),
            'IgnorePhysDiff'     => $this->boolStr($s->ignore_phys_diff),
            'IgnoreNegStock'     => $this->boolStr($s->ignore_neg_stock),
            'TreatSalesAsMfg'    => $this->boolStr($s->treat_sales_as_mfg),
            'TreatPurchConsumed' => $this->boolStr($s->treat_purch_consumed),
            'TreatRejectsScrap'  => $this->boolStr($s->treat_rejects_scrap),
            'IsCostCentresOn'    => $this->boolStr($s->is_cost_centres_on),
            'IsCostTrackingOn'   => $this->boolStr($s->is_cost_tracking_on),
            // Legacy VAT
            'IsEntryTaxApplicable' => $s->is_entry_tax_applicable ?? '',
            'IsRateInclusiveVAT'   => $s->is_rate_inclusive_vat   ?? '',
            'VATBaseUnit'          => $s->vat_base_unit            ?? '',
            // Arrays
            'PartNos'            => $s->part_nos         ?? [],
            'Aliases'            => $s->aliases          ?? [],
            'BatchAllocations'   => collect($s->batch_allocations ?? [])->map(fn ($ba) => [
                'GodownName'     => $ba['GodownName']     ?? '',
                'GodownID'       => (int) ($ba['GodownID'] ?? 0),
                'BatchName'      => $ba['BatchName']      ?? '',
                'MFDON'          => $ba['MFDON']          ?? '',
                'ExpiryPeriod'   => $ba['ExpiryPeriod']   ?? '',
                'OpeningBalnace' => (float) ($ba['OpeningBalnace'] ?? $ba['OpeningBalance'] ?? 0),
                'Rate'           => (float) ($ba['Rate']   ?? 0),
                'OpeningValue'   => (float) ($ba['OpeningValue'] ?? 0),
            ])->all(),
            'GSTDetailsList'     => $s->gst_details_list  ?? [],
            'HSNDetailsList'     => $s->hsn_details_list  ?? [],
            'VATDetails'         => $s->vat_details        ?? [],
        ]))->values()->all();
    }

    public function formatStockGroups(Collection $groups): array
    {
        return $groups->map(fn ($g) => $this->dropNulls([
            'AccobotId'               => $g->id,
            'TallyId'                 => $g->tally_id,
            'AlterID'                 => $g->alter_id,
            'Action'                  => $g->action,
            'Name'                    => $g->name,
            'Parent'                  => $g->parent_name ?? '',
            'Aliases'                 => $g->aliases ?? [],
            // Inventory Behaviour
            'CostingMethod'           => $g->costing_method    ?? '',
            'ValuationMethod'         => $g->valuation_method  ?? '',
            'IsBatchWiseOn'           => $this->boolStr($g->is_batch_wise_on),
            'IsPerishableOn'          => $this->boolStr($g->is_perishable_on),
            'IsAddable'               => $this->boolStr($g->is_addable),
            'IgnorePhysicalDifference'=> $this->boolStr($g->ignore_phys_diff),
            'IgnoreNegativeStock'     => $this->boolStr($g->ignore_neg_stock),
            'TreatSalesAsManufactured'=> $this->boolStr($g->treat_sales_as_mfg),
            'TreatPurchasesAsConsumed'=> $this->boolStr($g->treat_purch_consumed),
            'TreatRejectsAsScrap'     => $this->boolStr($g->treat_rejects_scrap),
            'HasMfgDate'              => $this->boolStr($g->has_mfg_date),
            'AllowUseOfExpiredItems'  => $this->boolStr($g->allow_expired_items),
            'IgnoreBatches'           => $this->boolStr($g->ignore_batches),
            'IgnoreGodowns'           => $this->boolStr($g->ignore_godowns),
            'Denominator'             => (int) ($g->denominator ?? 1),
            'Conversion'              => (float) ($g->conversion ?? 0),
            'GSTDetails'              => $g->gst_details  ?? [],
            'HSNDetails'              => $g->hsn_details  ?? [],
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
            'Parent'    => $c->parent_name ?? '',
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
        return $this->formatVouchers($vouchers, function (array $base, $v): array {
            // Tally Contra: both ledger entries have IsPartyLedger=Yes.
            // From account (Cr, IsDeemedPositive=No) also appears as PartyName on the header.
            if (empty($base['PartyName'])) {
                $fromEntry = $v->ledgerEntries->first(fn ($le) => !$le->is_deemed_positive);
                if ($fromEntry) $base['PartyName'] = $fromEntry->ledger_name;
            }
            // Ensure all Contra ledger entries are flagged as party ledger (Tally requirement)
            $base['ledgerentries'] = array_map(function ($le) {
                $le['IsPartyLedger'] = 'Yes';
                return $le;
            }, $base['ledgerentries']);
            return $base;
        });
    }

    public function formatJournalVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatAllVouchers(Collection $vouchers): array
    {
        return $this->formatVouchers($vouchers);
    }

    public function formatGodowns(Collection $godowns): array
    {
        return $godowns->map(fn ($g) => $this->dropNulls([
            'AccobotId'  => $g->id,
            'TallyId'    => $g->tally_id,
            'AlterID'    => $g->alter_id,
            'Action'     => $g->action,
            'Guid'       => $g->guid       ?? '',
            'Name'       => $g->name,
            'Under'      => $g->under      ?? '',
            'Aliases'    => $g->aliases    ?? [],
            'HasNoSpace' => $this->boolStr($g->has_no_space),
            'HasNoStock' => $this->boolStr($g->has_no_stock),
            'IsExternal' => $this->boolStr($g->is_external),
            'IsInternal' => $this->boolStr($g->is_internal),
            'Address'    => $g->address    ?? [],
        ]))->values()->all();
    }

    public function formatUnits(Collection $units): array
    {
        return $units->map(fn ($u) => $this->dropNulls([
            'AccobotId'            => $u->id,
            'TallyId'              => $u->tally_id,
            'AlterID'              => $u->alter_id,
            'Action'               => $u->action,
            'Guid'                 => $u->guid          ?? '',
            'Name'                 => $u->name,
            'Symbol'               => $u->symbol        ?? $u->name,
            'FormalName'           => $u->formal_name   ?? '',
            'OriginalName'         => $u->original_name ?? '',
            'DecimalPlaces'        => (int) ($u->decimal_places ?? 0),
            'UQC'                  => $u->uqc           ?? '',
            'IsSimpleUnit'         => $this->boolStr($u->is_simple_unit ?? true),
            'IsGSTExcluded'        => $u->is_gst_excluded ?? '',
            'CONVERSION'           => (float) ($u->conversion ?? 0),
            'ReportingUQCDetails'  => $u->reporting_uqc_details ?? [],
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
            'ApplicableFrom'     => $s->applicable_from?->format('Ymd'),
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
            'Guid'               => $g->guid,
            'Name'               => $g->name,
            'Under'              => $g->under,
            'CostCentreCategory' => $g->cost_centre_category,
            'Aliases'            => $g->aliases ?? [],
            'SalaryDetails'      => $g->salary_details ?? [],
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
            'JoiningDate'    => $e->date_of_joining?->format('Ymd'),
            'ResignationDate' => $e->date_of_leaving?->format('Ymd'),
            'DOB'            => $e->date_of_birth?->format('Ymd'),
            'Gender'         => $e->gender,
            'FatherName'     => $e->father_name,
            'SpouseName'     => $e->spouse_name,
            'ContactNumber'  => $e->contact_number,
            'Email'          => $e->email_address,
            'Address'        => $e->address ?? [],
            'Aliases'        => $e->aliases ?? [],
            'SalaryDetails'  => $e->salary_details ?? [],
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
            'AccobotId'       => $t->id,
            'TallyId'         => $t->tally_id,
            'AlterID'         => $t->alter_id,
            'Action'          => $t->action,
            'Guid'            => $t->guid,
            'Name'            => $t->name,
            'Under'           => $t->under,
            'AttendanceType'  => $t->attendance_type,
            'AttendancePeriod'=> $t->attendance_period,
            'Aliases'         => $t->aliases ?? [],
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
            'VoucherDate'   => $v->voucher_date?->format('Ymd'),
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
            'VoucherDate'   => $v->voucher_date?->format('Ymd'),
            'Narration'     => $v->narration,
            'EmployeeAllocations' => $v->employeeAllocations->map(fn ($a) => $this->dropNulls([
                'EmployeeName'      => $a->employee_name,
                'EmployeeGroup'     => $a->employee_group,
                'AttendanceEntries' => $a->entries ?? [],
            ]))->values()->all(),
        ]))->values()->all();
    }

    // ── Private ────────────────────────────────────────────────────────────────

    private function formatVouchers(Collection $vouchers, ?callable $postProcess = null): array
    {
        return $vouchers->map(function ($v) use ($postProcess) {
            $base = $this->dropNulls([
                'AccobotId'     => $v->id,
                'TallyId'       => $v->tally_id,
                'AlterID'       => $v->alter_id,
                'Action'        => $v->action,
                'VoucherType'     => $v->voucher_type,
                'VoucherBaseType' => $v->voucher_base_type,
                'VoucherNumber'   => $v->voucher_number,
                'VoucherDate'   => $v->voucher_date?->format('Ymd'),
                'Reference'     => $v->reference ?? '',
                'ReferenceDate' => $this->formatDateStr($v->reference_date),
                'PartyName'     => $v->party_name ?? '',
                'Voucher_Total'  => (float) $v->voucher_total,
                'IsInvoice'     => $this->boolStr($v->is_invoice),
                'PlaceOfSupply' => $v->place_of_supply ?? '',
                'VoucherCostCentre' => $v->cost_centre ?? '',
                'IsDeleted'         => $this->boolStr($v->is_deleted),

                // Dispatch / shipping
                'DeliveryNoteNo'   => $v->delivery_note_no ?? '',
                'DeliveryNoteDate' => $this->formatDateStr($v->delivery_note_date),
                'DispatchDocNo'    => $v->dispatch_doc_no ?? '',
                'DispatchThrough'  => $v->dispatch_through ?? '',
                'Destination'      => $v->destination ?? '',
                'CarrierName'      => $v->carrier_name ?? '',
                'LRNo'             => $v->lr_no ?? '',
                'LRDate'           => $this->formatDateStr($v->lr_date),
                'MotorVehicleNo'   => $v->motor_vehicle_no ?? '',

                // Order
                'OrderNo'          => $v->order_no ?? '',
                'OrderDate'        => $this->formatDateStr($v->order_date),
                'TermsOfPayment'   => $v->terms_of_payment ?? '',
                'OtherReferences'  => $v->other_references ?? '',
                'TermsOfDelivery'  => $v->terms_of_delivery ?? '',

                // Buyer
                'BuyerName'                => $v->buyer_name ?? '',
                'BuyerAlias'               => $v->buyer_alias ?? '',
                'BuyerGSTIN'               => $v->buyer_gstin ?? '',
                'BuyerPinCode'             => $v->buyer_pin_code ?? '',
                'BuyerState'               => $v->buyer_state ?? '',
                'BuyerCountryName'         => $v->buyer_country ?: 'India',
                'BuyerGSTRegistrationType' => $v->buyer_gst_registration_type ?? '',
                'BuyerEmail'               => $v->buyer_email ?? '',
                'BuyerMobile'              => $v->buyer_mobile ?? '',
                'BuyerAddress' => is_array($v->buyer_address)
                    ? $v->buyer_address
                    : (is_string($v->buyer_address) && $v->buyer_address !== ''
                        ? array_map(fn($l) => ['BuyerAddress' => $l], explode("\n", $v->buyer_address))
                        : []),

                // Consignee
                'ConsigneeName'                => $v->consignee_name ?? '',
                'ConsigneeGSTIN'               => $v->consignee_gstin ?? '',
                'ConsigneeTallyGroup'          => $v->consignee_tally_group ?? '',
                'ConsigneePinCode'             => $v->consignee_pin_code ?? '',
                'ConsigneeState'               => $v->consignee_state ?? '',
                'ConsigneeCountryName'         => $v->consignee_country ?: 'India',
                'ConsigneeGSTRegistrationType' => $v->consignee_gst_registration_type ?? '',
                'ConsigneeAddress' => is_array($v->consignee_address)
                    ? $v->consignee_address
                    : (is_string($v->consignee_address) && $v->consignee_address !== ''
                        ? array_map(fn($l) => ['ConsigneeAddress' => $l], explode("\n", $v->consignee_address))
                        : []),

                'Narration'           => $v->narration ?? '',
                'EWayBillDetails'     => $v->eway_bill_details ?? [],
                'CategoryEntries'     => $v->category_entries ?? [],
                'IRN'                 => $v->irn,
                'AcknowledgementNo'   => $v->acknowledgement_no,
                'AcknowledgementDate' => $v->acknowledgement_date,
                'QRCode'              => $v->qr_code,
            ]);

            $base['InventoryEntries'] = $v->inventoryEntries->map(fn ($ie) => $this->dropNulls([
                'StockItemName'    => $ie->stock_item_name,
                'ItemCode'         => $ie->item_code ?? '',
                'GroupName'        => $ie->group_name ?? '',
                'HSNCode'          => $ie->hsn_code ?? '',
                'Unit'             => $ie->unit,
                'IGSTRate'         => (float) $ie->igst_rate,
                'CessRate'         => (float) $ie->cess_rate,
                'IsDeemedPositive' => $this->boolStr($ie->is_deemed_positive),
                'ActualQty'        => (float) $ie->actual_qty,
                'BilledQty'        => (float) $ie->billed_qty,
                'Rate'             => (float) $ie->rate,
                'DiscountPercent'  => (float) $ie->discount_percent,
                'Amount'           => (float) $ie->amount,
                'TaxAmount'        => (float) $ie->tax_amount,
                'MRP'              => (float) ($ie->mrp ?? 0),
                'SalesLedger'           => $ie->sales_ledger ?? '',
                'GodownName'            => $ie->godown_name ?? '',
                'BatchName'             => $ie->batch_name ?? '',
                'BatchAllocations'      => $ie->batch_allocations ?? [],
                'AccountingAllocations' => $this->resolveAccountingAllocations($ie),
            ]))->values()->all();

            $base['ledgerentries'] = $v->ledgerEntries->map(fn ($le) => $this->dropNulls([
                'LedgerName'            => $le->ledger_name,
                'LedgerGroup'           => $le->ledger_group ?? '',
                'LedgerAmount'          => (float) $le->ledger_amount,
                'IsDeemedPositive'      => $this->boolStr($le->is_deemed_positive),
                'IsPartyLedger'         => $this->boolStr($le->is_party_ledger),
                'IGSTRate'              => $le->igst_rate ?? '',
                'HSNCode'               => $le->hsn_code ?? '',
                'Cess_Rate'             => $le->cess_rate ?? '',
                'BillsAllocation'       => $le->bills_allocation ?? [],
                'BankAllocationDetails' => $le->bank_allocation_details ?? [],
                'CategoryAllocation'    => $le->category_allocation ?? [],
            ]))->values()->all();

            return $postProcess ? $postProcess($base, $v) : $base;
        })->values()->all();
    }

    private function resolveAccountingAllocations($ie): array
    {
        if (!empty($ie->accounting_allocations)) {
            return array_map(fn($aa) => array_merge($aa, [
                'GSTClassification' => $this->normGSTClass($aa['GSTClassification'] ?? ''),
                'IGSTRate'          => (float) ($aa['IGSTRate'] ?? 0),
                'Amount'            => (float) ($aa['Amount'] ?? 0),
            ]), $ie->accounting_allocations);
        }
        if ($ie->sales_ledger) {
            $igst = (float) ($ie->igst_rate ?? 0);
            return [[
                'LedgerName'        => $ie->sales_ledger,
                'LedgerGroup'       => '',
                'GSTClassification' => $this->normGSTClass($igst > 0 ? 'Taxable' : 'Not Applicable'),
                'IGSTRate'          => $igst,
                'Amount'            => (float) ($ie->amount ?? 0),
            ]];
        }
        return [];
    }

    // Tally prefixes GST classification values with the EOT character (U+0004).
    private function normGSTClass(string $val): string
    {
        $val = ltrim($val, "\u{0004} ");
        if ($val === '') return '';
        return "\u{0004} {$val}";
    }

    private function formatDateStr(?string $dateStr): string
    {
        if (!$dateStr) return '';
        try {
            return \Carbon\Carbon::parse($dateStr)->format('Ymd');
        } catch (\Throwable $e) {
            return $dateStr;
        }
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
