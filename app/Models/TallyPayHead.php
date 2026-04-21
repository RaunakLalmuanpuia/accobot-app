<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyPayHead extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'pay_head_type', 'income_type', 'pay_slip_name', 'under_group',
        'ledger_name', 'calculation_type', 'leave_type', 'rate', 'rate_period',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'rate'           => 'float',
        'is_active'      => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
