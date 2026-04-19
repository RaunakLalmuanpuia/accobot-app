<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyLedgerGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'under_id', 'under_name',
        'nature_of_group', 'is_revenue', 'affects_gross', 'is_addable',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'is_revenue'     => 'boolean',
        'affects_gross'  => 'boolean',
        'is_addable'     => 'boolean',
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
