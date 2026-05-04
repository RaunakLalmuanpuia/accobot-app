<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantBankAccount extends Model
{
    protected $fillable = [
        'tenant_id',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc_code',
        'account_type',
        'branch',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function makePrimary(): void
    {
        $this->tenant->bankAccounts()->where('id', '!=', $this->id)->update(['is_primary' => false]);
        $this->update(['is_primary' => true]);
    }
}
