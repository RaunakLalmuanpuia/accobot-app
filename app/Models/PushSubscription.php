<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id', 'endpoint', 'public_key', 'auth_token', 'user_agent',
    ];

    protected $hidden = ['auth_token', 'public_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
