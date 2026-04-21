<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyGodown extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'guid', 'name', 'under',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
