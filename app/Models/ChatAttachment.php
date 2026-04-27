<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ChatAttachment extends Model
{
    use HasUuids, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'chat_message_id', 'user_id', 'disk',
        'path', 'original_filename', 'mime_type', 'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    protected $appends = ['signed_url'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'chat_message_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getSignedUrlAttribute(): ?string
    {
        if (! Storage::disk($this->disk)->exists($this->path)) {
            return null;
        }

        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(30));
    }
}
