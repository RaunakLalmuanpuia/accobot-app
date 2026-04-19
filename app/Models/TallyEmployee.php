<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyEmployee extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'tally_id', 'alter_id', 'action',
        'name', 'employee_number', 'group_name',
        'designation', 'employee_function', 'department',
        'date_of_joining', 'date_of_leaving', 'date_of_birth', 'gender',
        'pan', 'aadhar', 'pf_number', 'uan_number', 'esi_number',
        'bank_name', 'bank_account_number', 'bank_ifsc',
        'addresses', 'salary_details',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'addresses'       => 'array',
        'salary_details'  => 'array',
        'date_of_joining' => 'date',
        'date_of_leaving' => 'date',
        'date_of_birth'   => 'date',
        'is_active'       => 'boolean',
        'last_synced_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
