<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyStockGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'parent_id', 'parent_name',
        'nature_of_group', 'should_add_quantities',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'should_add_quantities' => 'boolean',
        'is_active'             => 'boolean',
        'last_synced_at'        => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
