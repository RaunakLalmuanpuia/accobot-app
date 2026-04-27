<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
        return $this->hasOne(ChatMessage::class)->latestOfMany('created_at');
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
}
