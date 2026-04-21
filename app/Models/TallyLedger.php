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
        'ledger_name', 'group_name', 'parent_group', 'ledger_category',
        'is_bill_wise_on', 'inventory_affected',
        'gstin_number', 'pan_number', 'gst_type',
        'mailing_name', 'mobile_number', 'contact_person',
        'contact_person_email', 'contact_person_email_cc',
        'contact_person_fax', 'contact_person_website', 'contact_person_mobile',
        'addresses', 'state_name', 'country_name', 'pin_code',
        'credit_period', 'credit_limit',
        'opening_balance', 'opening_balance_type',
        'aliases', 'description', 'notes',
        'is_active', 'last_synced_at',
        'mapped_client_id', 'mapped_vendor_id',
    ];

    protected $casts = [
        'is_bill_wise_on'  => 'boolean',
        'inventory_affected' => 'boolean',
        'is_active'        => 'boolean',
        'addresses'        => 'array',
        'aliases'          => 'array',
        'credit_limit'                => 'decimal:2',
        'opening_balance'             => 'decimal:2',
        'last_synced_at'              => 'datetime',
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
