<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\ChatRoomMember;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatRoom extends Model
{
    use HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'type', 'is_system', 'created_by_user_id',
    ];

    protected $casts = [
        'is_system'  => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ChatRoomMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_members')
            ->withPivot('role', 'joined_at', 'last_read_message_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class)->orderBy('created_at', 'asc');
    }

    public function latestMessage(): HasOne
    {
        // ofMany/latestOfMany always adds MAX(id) as a tie-breaker join, which fails on
        // UUID primary keys in PostgreSQL. Use a correlated subquery on created_at instead.
        return $this->hasOne(ChatMessage::class, 'chat_room_id')
            ->whereRaw('"chat_messages"."created_at" = (
                select max("created_at") from "chat_messages" as "lm"
                where "lm"."chat_room_id" = "chat_messages"."chat_room_id"
                and "lm"."deleted_at" is null
            )');
    }

    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->whereHas('members', fn ($q) => $q->where('user_id', $userId));
    }

    public function scopeGroup(Builder $query): void
    {
        $query->where('type', 'group');
    }

    public function scopeSystem(Builder $query): void
    {
        $query->where('is_system', true);
    }

    public static function notificationsChannelForTenant(string $tenantId): self
    {
        return static::withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['tenant_id' => $tenantId, 'type' => 'notifications', 'is_system' => true],
                ['name' => 'Notifications']
            );
    }

    public static function generalChannelForTenant(string $tenantId): self
    {
        return static::withoutGlobalScope('tenant')
            ->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => 'General', 'is_system' => true],
                ['type' => 'group']
            );
    }

    /** Add a user to the General room if their role qualifies. Idempotent. */
    public static function addToGeneralIfQualified(string $tenantId, int $userId, string $roleName): void
    {
        $qualifyingRoles = ['owner', 'TenantAdmin', 'ExternalAccountant', 'CAManager'];

        if (! in_array($roleName, $qualifyingRoles, strict: true)) {
            return;
        }

        $room = self::generalChannelForTenant($tenantId);

        ChatRoomMember::firstOrCreate(
            ['chat_room_id' => $room->id, 'user_id' => $userId],
            ['tenant_id' => $tenantId, 'role' => 'member', 'joined_at' => now()]
        );
    }
}
