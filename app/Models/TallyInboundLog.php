<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyInboundLog extends Model
{
    protected $fillable = [
        'tenant_id',
        'tally_connection_id',
        'endpoint',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }
}