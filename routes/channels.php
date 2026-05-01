<?php

use App\Models\ChatRoomMember;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Default user model channel (kept from Laravel scaffold)
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Presence channel for a specific chat room.
 * Channel: presence-room.{tenantId}.{roomId}  (Laravel adds the "presence-" prefix automatically)
 */
Broadcast::channel('room.{tenantId}.{roomId}', function ($user, string $tenantId, string $roomId) {
    if (! $user->tenants()->where('tenants.id', $tenantId)->exists()) {
        return false;
    }

    $member = ChatRoomMember::where('chat_room_id', $roomId)
        ->where('user_id', $user->id)
        ->first();

    if (! $member) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});

/**
 * Private channel for per-user system notifications.
 * Wire channel: private-user.{userId}  (Laravel adds the "private-" prefix automatically)
 */
Broadcast::channel('user.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

/**
 * Private channel for tenant-wide notifications room broadcast.
 * Wire channel: private-tenant.{tenantId}.notifications  (Laravel adds the "private-" prefix automatically)
 */
Broadcast::channel('tenant.{tenantId}.notifications', function ($user, string $tenantId) {
    return $user->tenants()->where('tenants.id', $tenantId)->exists();
});
