<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyLedger extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'guid', 'currency_name', 'ledger_country_isd_code',
        'ledger_name', 'group_name', 'parent_group', 'ledger_category',
        'is_bill_wise_on', 'inventory_affected', 'is_cost_centres_on', 'is_cost_tracking_on',
        // GST / Tax
        'gstin_number', 'pan_number',
        'gst_type_ledger', 'gst_registration_type', 'gst_applicable_from', 'is_gst_applicable',
        'is_sez_party', 'is_transporter', 'is_common_party', 'is_other_territory_assessee',
        'appropriate_for', 'is_rcm_applicable',
        'pan_applicable_from', 'name_on_pan',
        'is_tds_applicable', 'tds_deductee_type', 'is_tds_projected',
        'is_tcs_applicable',
        'vat_tin_number', 'tax_type', 'used_for_tax_type',
        'registration_number', 'service_category', 'importer_exporter_code',
        // Contact
        'mailing_name', 'mobile_number', 'contact_person',
        'contact_person_email', 'contact_person_email_cc',
        'contact_person_fax', 'contact_person_website', 'contact_person_mobile',
        // Address
        'addresses', 'state_name', 'country_name', 'pin_code',
        // Credit
        'credit_period', 'credit_limit', 'is_credit_days_check_on', 'override_credit_limit',
        // Opening Balance
        'opening_balance', 'opening_balance_type',
        // Interest
        'type_of_interest_on', 'is_interest_on', 'is_interest_on_bill_wise',
        'override_interest', 'override_adv_interest', 'is_interest_incl_last_day',
        'interest_incl_day_of_addition', 'interest_incl_day_of_deduction',
        // Bank flat fields
        'bank_account_holder_name', 'swift_code', 'branch_name', 'bank_bsr_code',
        'default_transfer_mode', 'is_cheque_printing_enabled',
        // Party / payroll flags
        'is_related_party', 'for_payroll', 'use_for_esi_eligibility',
        'is_e_cash_ledger', 'ignore_mismatch_with_warning', 'ignore_tds_exempt',
        'it_exempt_applicable',
        // JSONB arrays
        'aliases', 'description', 'notes', 'bank_details', 'bill_allocations',
        'gst_registration_details', 'cheque_ranges', 'transfer_mode_limits',
        'interest_collection', 'tds_category_details', 'tcs_category_details',
        'contact_details', 'deduct_in_same_vch_rules', 'led_multi_address_list',
        // Status
        'is_active', 'last_synced_at',
        'mapped_client_id', 'mapped_vendor_id',
    ];

    protected $casts = [
        'is_bill_wise_on'               => 'boolean',
        'inventory_affected'            => 'boolean',
        'is_cost_centres_on'            => 'boolean',
        'is_cost_tracking_on'           => 'boolean',
        'is_gst_applicable'             => 'boolean',
        'is_sez_party'                  => 'boolean',
        'is_transporter'                => 'boolean',
        'is_common_party'               => 'boolean',
        'is_other_territory_assessee'   => 'boolean',
        'is_rcm_applicable'             => 'boolean',
        'is_tds_applicable'             => 'boolean',
        'is_tds_projected'              => 'boolean',
        'is_tcs_applicable'             => 'boolean',
        'is_credit_days_check_on'       => 'boolean',
        'override_credit_limit'         => 'boolean',
        'is_interest_on'                => 'boolean',
        'is_interest_on_bill_wise'      => 'boolean',
        'override_interest'             => 'boolean',
        'override_adv_interest'         => 'boolean',
        'is_interest_incl_last_day'     => 'boolean',
        'interest_incl_day_of_addition' => 'boolean',
        'interest_incl_day_of_deduction'=> 'boolean',
        'is_cheque_printing_enabled'    => 'boolean',
        'is_related_party'              => 'boolean',
        'for_payroll'                   => 'boolean',
        'use_for_esi_eligibility'       => 'boolean',
        'ignore_mismatch_with_warning'  => 'boolean',
        'ignore_tds_exempt'             => 'boolean',
        'is_active'                     => 'boolean',
        'credit_limit'                  => 'decimal:2',
        'opening_balance'               => 'decimal:2',
        'addresses'                     => 'array',
        'aliases'                       => 'array',
        'bank_details'                  => 'array',
        'bill_allocations'              => 'array',
        'gst_registration_details'      => 'array',
        'cheque_ranges'                 => 'array',
        'transfer_mode_limits'          => 'array',
        'interest_collection'           => 'array',
        'tds_category_details'          => 'array',
        'tcs_category_details'          => 'array',
        'contact_details'               => 'array',
        'deduct_in_same_vch_rules'      => 'array',
        'led_multi_address_list'        => 'array',
        'last_synced_at'                => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mappedClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'mapped_client_id');
    }

    public function mappedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'mapped_vendor_id');
    }
}
