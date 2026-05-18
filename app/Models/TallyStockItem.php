<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyStockItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        // Identity
        'guid', 'name', 'description', 'remarks', 'aliases', 'part_nos',
        // Classification
        'stock_group_id', 'stock_group_name', 'stock_category_id', 'category_name',
        // Units
        'unit_id', 'unit_name', 'alternate_unit', 'conversion', 'denominator',
        'reporting_uom', 'reporting_uom_date',
        // GST & Tax
        'is_gst_applicable', 'taxability', 'calculation_type',
        'igst_rate', 'sgst_rate', 'cgst_rate', 'cess_rate',
        'hsn_code', 'hsn_desc', 'type_of_supply',
        // TCS
        'tcs_applicable', 'tcs_category',
        // Pricing
        'mrp_rate', 'inclusive_tax', 'modify_mrp_rate', 'calc_on_mrp', 'mrp_incl_of_tax',
        'basic_rate_of_excise',
        // Costing / Valuation
        'costing_method', 'valuation_method',
        // Default Ledgers
        'sales_ledger', 'sales_ledger_rate', 'purchase_ledger', 'purchase_ledger_rate',
        // Stock Levels
        'opening_balance', 'opening_rate', 'opening_value',
        'closing_balance', 'closing_rate', 'closing_value',
        // Inventory Behaviour
        'is_batch_wise', 'is_perishable', 'has_mfg_date', 'allow_expired_items',
        'ignore_batches', 'ignore_godowns', 'ignore_phys_diff', 'ignore_neg_stock',
        'treat_sales_as_mfg', 'treat_purch_consumed', 'treat_rejects_scrap',
        'is_cost_centres_on', 'is_cost_tracking_on',
        // Legacy VAT
        'is_entry_tax_applicable', 'is_rate_inclusive_vat', 'vat_base_unit',
        // Batch / Godown
        'batch_allocations',
        // Structured arrays
        'gst_details_list', 'hsn_details_list', 'vat_details',
        // Meta
        'is_active', 'last_synced_at', 'mapped_product_id',
    ];

    protected $casts = [
        'aliases'            => 'array',
        'part_nos'           => 'array',
        'batch_allocations'  => 'array',
        'gst_details_list'   => 'array',
        'hsn_details_list'   => 'array',
        'vat_details'        => 'array',
        'is_gst_applicable'  => 'boolean',
        'inclusive_tax'      => 'boolean',
        'modify_mrp_rate'    => 'boolean',
        'calc_on_mrp'        => 'boolean',
        'mrp_incl_of_tax'    => 'boolean',
        'is_batch_wise'      => 'boolean',
        'is_perishable'      => 'boolean',
        'has_mfg_date'       => 'boolean',
        'allow_expired_items'   => 'boolean',
        'ignore_batches'        => 'boolean',
        'ignore_godowns'        => 'boolean',
        'ignore_phys_diff'      => 'boolean',
        'ignore_neg_stock'      => 'boolean',
        'treat_sales_as_mfg'    => 'boolean',
        'treat_purch_consumed'  => 'boolean',
        'treat_rejects_scrap'   => 'boolean',
        'is_cost_centres_on'    => 'boolean',
        'is_cost_tracking_on'   => 'boolean',
        'is_active'          => 'boolean',
        'igst_rate'          => 'decimal:2',
        'sgst_rate'          => 'decimal:2',
        'cgst_rate'          => 'decimal:2',
        'cess_rate'          => 'decimal:2',
        'mrp_rate'           => 'decimal:2',
        'sales_ledger_rate'  => 'decimal:2',
        'purchase_ledger_rate' => 'decimal:2',
        'basic_rate_of_excise' => 'decimal:4',
        'opening_balance'    => 'decimal:4',
        'opening_rate'       => 'decimal:2',
        'opening_value'      => 'decimal:2',
        'closing_balance'    => 'decimal:4',
        'closing_rate'       => 'decimal:2',
        'closing_value'      => 'decimal:2',
        'last_synced_at'     => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stockGroup(): BelongsTo
    {
        return $this->belongsTo(TallyStockGroup::class, 'stock_group_name', 'name');
    }

    public function stockCategory(): BelongsTo
    {
        return $this->belongsTo(TallyStockCategory::class, 'category_name', 'name');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(TallyUnit::class, 'unit_name', 'name');
    }

    public function alternateUnit(): BelongsTo
    {
        return $this->belongsTo(TallyUnit::class, 'alternate_unit', 'name');
    }

    public function salesLedger(): BelongsTo
    {
        return $this->belongsTo(TallyLedger::class, 'sales_ledger', 'ledger_name');
    }

    public function purchaseLedger(): BelongsTo
    {
        return $this->belongsTo(TallyLedger::class, 'purchase_ledger', 'ledger_name');
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(TallyVoucherInventoryEntry::class, 'tally_stock_item_id');
    }

    public function mappedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mapped_product_id');
    }
}
