<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyStockItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'description', 'remarks', 'aliases', 'part_nos',
        'stock_group_id', 'stock_group_name', 'stock_category_id', 'category_name',
        'unit_id', 'unit_name', 'alternate_unit', 'conversion', 'denominator',
        'is_gst_applicable', 'taxability', 'calculation_type',
        'igst_rate', 'sgst_rate', 'cgst_rate', 'cess_rate', 'hsn_code',
        'mrp_rate',
        'opening_balance', 'opening_rate', 'opening_value',
        'closing_balance', 'closing_rate', 'closing_value',
        'batch_allocations',
        'is_active', 'last_synced_at',
        'mapped_product_id',
    ];

    protected $casts = [
        'aliases'            => 'array',
        'part_nos'           => 'array',
        'batch_allocations'  => 'array',
        'is_gst_applicable' => 'boolean',
        'is_active'         => 'boolean',
        'igst_rate'         => 'decimal:2',
        'sgst_rate'         => 'decimal:2',
        'cgst_rate'         => 'decimal:2',
        'cess_rate'         => 'decimal:2',
        'mrp_rate'          => 'decimal:2',
        'opening_balance'   => 'decimal:4',
        'opening_rate'               => 'decimal:2',
        'opening_value'              => 'decimal:2',
        'closing_balance'            => 'decimal:4',
        'closing_rate'               => 'decimal:2',
        'closing_value'              => 'decimal:2',
        'last_synced_at'             => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function mappedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'mapped_product_id');
    }
}
