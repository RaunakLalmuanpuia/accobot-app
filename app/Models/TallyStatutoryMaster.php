<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyStatutoryMaster extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'statutory_type', 'registration_number',
        'state_code', 'registration_type', 'pan', 'tan',
        'applicable_from', 'details',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'details'        => 'array',
        'applicable_from'=> 'date',
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
