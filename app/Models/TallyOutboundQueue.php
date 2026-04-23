<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TallyOutboundQueue extends Model
{
    public $timestamps = false;

    protected $table = 'tally_outbound_queue';

    protected $fillable = [
        'tenant_id', 'entity_type', 'entity_id', 'status', 'queued_at', 'confirmed_at',
    ];

    protected $casts = [
        'queued_at'    => 'datetime',
        'confirmed_at' => 'datetime',
    ];
}
