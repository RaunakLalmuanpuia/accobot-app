<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyReport extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'report_type',
        'period_from', 'period_to',
        'data', 'generated_at', 'synced_at',
    ];

    protected $casts = [
        'period_from'  => 'date',
        'period_to'    => 'date',
        'data'         => 'array',
        'generated_at' => 'datetime',
        'synced_at'    => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
