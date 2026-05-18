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
        'name', 'employee_number', 'parent',
        'designation', 'employee_function', 'location',
        'date_of_joining', 'date_of_leaving', 'date_of_birth', 'gender',
        'father_name', 'spouse_name', 'aliases',
        'contact_number', 'email_address', 'address', 'salary_details',
        'is_active', 'last_synced_at',
    ];

    protected $casts = [
        'aliases'         => 'array',
        'address'         => 'array',
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

    public function employeeGroup(): BelongsTo
    {
        return $this->belongsTo(TallyEmployeeGroup::class, 'parent', 'name');
    }
}
