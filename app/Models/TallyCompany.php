<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyCompany extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'tenant_id',
        'company_guid',
        'tally_id',
        'action',
        'company_name',
        'address',
        'state',
        'country',
        'tally_serial_no',
        'licence_type',
        'licence_number',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }
}
