<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallySyncLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'entity', 'direction', 'status',
        'triggered_manually',
        'records_created', 'records_updated', 'records_skipped', 'records_deleted', 'records_failed',
        'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'triggered_manually' => 'boolean',
        'started_at'         => 'datetime',
        'completed_at'       => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
