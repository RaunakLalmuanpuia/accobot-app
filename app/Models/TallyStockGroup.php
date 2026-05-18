<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyStockGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        // Identity
        'name', 'parent_name', 'aliases',
        // Inventory Behaviour
        'costing_method', 'valuation_method',
        'is_batch_wise_on', 'is_perishable_on', 'is_addable',
        'ignore_phys_diff', 'ignore_neg_stock',
        'treat_sales_as_mfg', 'treat_purch_consumed', 'treat_rejects_scrap',
        'has_mfg_date', 'allow_expired_items', 'ignore_batches', 'ignore_godowns',
        'denominator', 'conversion',
        // GST / HSN arrays
        'gst_details', 'hsn_details',
        // Meta
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'aliases'           => 'array',
        'gst_details'       => 'array',
        'hsn_details'       => 'array',
        'is_batch_wise_on'  => 'boolean',
        'is_perishable_on'  => 'boolean',
        'is_addable'        => 'boolean',
        'ignore_phys_diff'  => 'boolean',
        'ignore_neg_stock'  => 'boolean',
        'treat_sales_as_mfg'   => 'boolean',
        'treat_purch_consumed' => 'boolean',
        'treat_rejects_scrap'  => 'boolean',
        'has_mfg_date'         => 'boolean',
        'allow_expired_items'  => 'boolean',
        'ignore_batches'       => 'boolean',
        'ignore_godowns'       => 'boolean',
        'is_active'         => 'boolean',
        'conversion'        => 'decimal:4',
        'last_synced_at'    => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TallyStockGroup::class, 'parent_name', 'name');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TallyStockGroup::class, 'parent_name', 'name');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(TallyStockItem::class, 'stock_group_name', 'name');
    }
}
