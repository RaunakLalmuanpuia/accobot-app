<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyUnit extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'guid', 'name', 'symbol', 'formal_name', 'original_name',
        'decimal_places', 'uqc',
        'is_simple_unit', 'is_gst_excluded', 'conversion',
        'reporting_uqc_details',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'reporting_uqc_details' => 'array',
        'is_simple_unit'        => 'boolean',
        'is_active'             => 'boolean',
        'conversion'            => 'decimal:4',
        'last_synced_at'        => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(TallyStockItem::class, 'unit_name', 'name');
    }

    public function stockItemsAlternate(): HasMany
    {
        return $this->hasMany(TallyStockItem::class, 'alternate_unit', 'name');
    }
}
