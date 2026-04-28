<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyCompany extends Model
{
    protected $fillable = [
        'tally_connection_id', 'tenant_id', 'company_guid', 'tally_id', 'action',
        // Core identity
        'company_name', 'formal_name', 'name_alias',
        'email', 'phone_number', 'fax_number', 'website', 'mobile_numbers',
        'address', 'address1', 'address2', 'address3', 'address4', 'address5',
        'state', 'prior_state', 'country', 'country_isd_code', 'pincode',
        'branch_name', 'branch_name2',
        'logo_path', 'price_level', 'connect_name', 'db_name',
        'company_number', 'statutory_version', 'corporate_identity_no',
        'tally_serial_no', 'licence_type', 'licence_number',
        // Tax
        'income_tax_number', 'sales_tax_number', 'interstate_st_number', 'ta_number',
        'gst_registration_number', 'gst_registration_type', 'gst_applicable_date',
        'gst_applicability', 'cmp_type_of_supply', 'hsn_applicability',
        'eway_bill_applicable_type', 'eway_bill_interstate_threshold',
        // Financial periods
        'starting_from', 'books_from', 'audited_upto', 'fifo_applicable_from', 'returns_start_from',
        'this_year_beg', 'this_year_end', 'prev_year_beg', 'prev_year_end',
        'this_quarter_beg', 'this_quarter_end', 'prev_quarter_beg', 'prev_quarter_end',
        // JSON blobs
        'feature_flags', 'deductor_details', 'legacy_tax_details',
    ];

    protected $casts = [
        'feature_flags'      => 'array',
        'deductor_details'   => 'array',
        'legacy_tax_details' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }
}
