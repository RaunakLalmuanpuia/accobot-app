<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TallyConnection extends Model
{
    protected $fillable = [
        'tenant_id', 'company_id', 'is_active',
        'inbound_token', 'inbound_token_last_used_at', 'last_synced_at',
    ];

    protected $hidden = ['inbound_token'];

    protected $casts = [
        'is_active'                  => 'boolean',
        'inbound_token_last_used_at' => 'datetime',
        'last_synced_at'             => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $conn) {
            $conn->inbound_token = Str::random(48);
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(TallyCompany::class, 'tally_connection_id');
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(TallySyncLog::class, 'tenant_id', 'tenant_id');
    }

    public function regenerateToken(): void
    {
        $this->update(['inbound_token' => Str::random(48)]);
    }
}
