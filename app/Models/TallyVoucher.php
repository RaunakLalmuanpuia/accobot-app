<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyVoucher extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'voucher_type', 'voucher_base_type', 'voucher_number', 'voucher_date',
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
        'consignee_gst_registration_type', 'consignee_address',
        'irn', 'acknowledgement_no', 'acknowledgement_date', 'qr_code',
        'eway_bill_details', 'category_entries',
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
        'buyer_address'      => 'array',
        'consignee_address'  => 'array',
        'eway_bill_details'  => 'array',
        'category_entries'   => 'array',
        'last_synced_at'     => 'datetime',
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

    // ── Type scopes ────────────────────────────────────────────────────────────
    public function scopeSales(Builder $q): Builder        { return $q->where('voucher_base_type', 'Sales'); }
    public function scopePurchase(Builder $q): Builder     { return $q->where('voucher_base_type', 'Purchase'); }
    public function scopeCreditNote(Builder $q): Builder   { return $q->where('voucher_base_type', 'Credit Note'); }
    public function scopeDebitNote(Builder $q): Builder    { return $q->where('voucher_base_type', 'Debit Note'); }
    public function scopeReceipt(Builder $q): Builder      { return $q->where('voucher_base_type', 'Receipt'); }
    public function scopePayment(Builder $q): Builder      { return $q->where('voucher_base_type', 'Payment'); }
    public function scopeContra(Builder $q): Builder       { return $q->where('voucher_base_type', 'Contra'); }
    public function scopeJournal(Builder $q): Builder      { return $q->where('voucher_base_type', 'Journal'); }
    public function scopePayroll(Builder $q): Builder      { return $q->where('voucher_base_type', 'Payroll'); }
    public function scopeAttendance(Builder $q): Builder   { return $q->where('voucher_base_type', 'Attendance'); }

    public function scopeInventory(Builder $q): Builder
    {
        return $q->whereIn('voucher_base_type', ['Sales', 'Purchase', 'Credit Note', 'Debit Note']);
    }

    public function scopeAccounting(Builder $q): Builder
    {
        return $q->whereIn('voucher_base_type', ['Receipt', 'Payment', 'Contra', 'Journal']);
    }
}
