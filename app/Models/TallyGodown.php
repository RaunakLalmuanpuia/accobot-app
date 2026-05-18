<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyGodown extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'guid', 'name', 'under', 'aliases',
        'has_no_space', 'has_no_stock', 'is_external', 'is_internal',
        'address',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'aliases'      => 'array',
        'address'      => 'array',
        'has_no_space' => 'boolean',
        'has_no_stock' => 'boolean',
        'is_external'  => 'boolean',
        'is_internal'  => 'boolean',
        'is_active'    => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(TallyGodown::class, 'under', 'name');
    }

    public function children(): HasMany
    {
        return $this->hasMany(TallyGodown::class, 'under', 'name');
    }

    public function inventoryEntries(): HasMany
    {
        return $this->hasMany(TallyVoucherInventoryEntry::class, 'godown_name', 'name');
    }
}
