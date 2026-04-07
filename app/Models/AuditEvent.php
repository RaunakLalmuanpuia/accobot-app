<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'occurred_at',
        'tenant_id',
        'actor_user_id',
        'actor_type',
        'impersonator_user_id',
        'event_type',
        'ip',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
        'metadata'    => 'array',
    ];

    // Append-only: never allow updates or deletes in application logic
    public static function boot(): void
    {
        parent::boot();

        static::updating(fn() => false);
        static::deleting(fn() => false);
    }

    /**
     * Log an audit event conveniently.
     */
    public static function log(
        string $eventType,
        array  $metadata = [],
        ?int   $actorUserId = null,
        ?string $tenantId = null,
        string $actorType = 'human',
    ): self {
        $request = request();
        $user    = $actorUserId ? null : auth()->user();

        return static::create([
            'occurred_at'          => now(),
            'tenant_id'            => $tenantId ?? $request->route('tenant')?->id,
            'actor_user_id'        => $actorUserId ?? $user?->id,
            'actor_type'           => $actorType,
            'impersonator_user_id' => session('impersonator_id'),
            'event_type'           => $eventType,
            'ip'                   => $request->ip(),
            'user_agent'           => $request->userAgent(),
            'metadata'             => $metadata,
        ]);
    }
}
