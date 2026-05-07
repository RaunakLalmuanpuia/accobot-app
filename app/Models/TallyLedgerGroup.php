<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyLedgerGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action', 'erp_id',
        'name', 'under_id', 'under_name', 'nature_of_group',
        'is_sub_ledger', 'is_deemed_positive', 'used_for_calculation',
        'method_to_allocate', 'is_addable', 'tds_category_details',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'is_sub_ledger'         => 'boolean',
        'is_deemed_positive'    => 'boolean',
        'used_for_calculation'  => 'boolean',
        'is_addable'            => 'boolean',
        'tds_category_details'  => 'array',
        'is_active'             => 'boolean',
        'last_synced_at'        => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
