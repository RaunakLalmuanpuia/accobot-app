<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasUuids, BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'chat_room_id', 'user_id', 'body', 'type',
        'metadata', 'reply_to_message_id', 'edited_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'edited_at'  => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['reaction_summary'];

    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(MessageReaction::class, 'chat_message_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatAttachment::class, 'chat_message_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_message_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(MessageRead::class, 'last_read_message_id', 'id');
    }

    public function getReactionSummaryAttribute(): array
    {
        return $this->reactions
            ->groupBy('emoji')
            ->map(fn ($group) => [
                'emoji' => $group->first()->emoji,
                'count' => $group->count(),
                'users' => $group->pluck('user_id'),
            ])
            ->values()
            ->toArray();
    }
}
