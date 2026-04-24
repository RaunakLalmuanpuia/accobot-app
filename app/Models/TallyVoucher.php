<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyVoucher extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'voucher_type', 'voucher_number', 'voucher_date',
        'reference', 'reference_date',
        'party_name', 'party_tally_ledger_id', 'voucher_total',
        'is_invoice', 'is_deleted',
        'place_of_supply', 'delivery_note_no', 'delivery_note_date',
        'dispatch_doc_no', 'dispatch_through', 'destination',
        'carrier_name', 'lr_no', 'lr_date', 'motor_vehicle_no',
        'order_no', 'order_date', 'terms_of_payment', 'terms_of_delivery', 'other_references',
        'buyer_name', 'buyer_alias', 'buyer_gstin', 'buyer_pin_code',
        'buyer_state', 'buyer_country', 'buyer_gst_registration_type',
        'buyer_email', 'buyer_mobile', 'buyer_address',
        'consignee_name', 'consignee_gstin', 'consignee_tally_group',
        'consignee_pin_code', 'consignee_state', 'consignee_country',
        'consignee_gst_registration_type',
        'irn', 'acknowledgement_no', 'acknowledgement_date', 'qr_code',
        'narration', 'cost_centre',
        'is_active', 'last_synced_at',
        'mapped_invoice_id', 'mapped_vendor_id',
    ];

    protected $casts = [
        'voucher_date'  => 'date',
        'voucher_total' => 'decimal:2',
        'is_invoice'    => 'boolean',
        'is_deleted'    => 'boolean',
        'is_active'     => 'boolean',
        'buyer_address' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function partyLedger(): BelongsTo
    {
        return $this->belongsTo(TallyLedger::class, 'party_tally_ledger_id');
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(TallyVoucherInventoryEntry::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(TallyVoucherLedgerEntry::class);
    }

    public function employeeAllocations(): HasMany
    {
        return $this->hasMany(TallyVoucherEmployeeAllocation::class);
    }

    public function mappedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'mapped_invoice_id');
    }

    public function mappedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'mapped_vendor_id');
    }
}
