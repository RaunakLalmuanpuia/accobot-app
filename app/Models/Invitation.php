<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class Invitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'role_id',
        'invited_by',
        'email',
        'token_hash',
        'expires_at',
        'accepted_at',
        'status',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'accepted_at' => 'datetime',
    ];

    // ── Token helpers ─────────────────────────────────────────────────

    /**
     * Generate a secure raw token, store its hash, return the raw token for use in URLs.
     */
    public static function generateToken(): array
    {
        $raw  = Str::random(64);
        $hash = hash('sha256', $raw);
        return [$raw, $hash];
    }

    public static function findByRawToken(string $rawToken): ?self
    {
        return static::where('token_hash', hash('sha256', $rawToken))->first();
    }

    // ── Relationships ─────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }
}
