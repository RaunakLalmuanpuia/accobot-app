<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyStockCategory extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'parent_name', 'aliases',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'aliases'        => 'array',
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TallyStockCategory::class, 'parent_name', 'name');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TallyStockCategory::class, 'parent_name', 'name');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(TallyStockItem::class, 'category_name', 'name');
    }
}
