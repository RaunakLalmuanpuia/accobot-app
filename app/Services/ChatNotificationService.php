<?php

namespace App\Services;

use App\Events\BroadcastChatMessage;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class ChatNotificationService
{
    /**
     * Send a system notification to tenant members:
     * - Posts a system message to the tenant's Notifications room
     * - Sends a Laravel notification (DB + broadcast + web push) to the target users
     *
     * @param  Collection<User>|null  $users  Null → all tenant members
     */
    public static function notify(
        string      $tenantId,
        string      $title,
        string      $body,
        string      $eventType,
        array       $data = [],
        ?Collection $users = null,
        bool        $postToGroupRooms = false,
    ): void {
        // Post to the Notifications chat room
        $notifRoom = ChatRoom::notificationsChannelForTenant($tenantId);

        $message = ChatMessage::create([
            'tenant_id'    => $tenantId,
            'chat_room_id' => $notifRoom->id,
            'user_id'      => null,
            'type'         => 'system',
            'body'         => $body,
            'metadata'     => ['event_type' => $eventType, ...$data],
        ]);

        BroadcastChatMessage::dispatch($message);

        // Also post to all group chat rooms so members see it inline
        if ($postToGroupRooms) {
            $groupRooms = ChatRoom::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('type', 'group')
                ->get();

            foreach ($groupRooms as $groupRoom) {
                $groupMessage = ChatMessage::create([
                    'tenant_id'    => $tenantId,
                    'chat_room_id' => $groupRoom->id,
                    'user_id'      => null,
                    'type'         => 'system',
                    'body'         => $body,
                    'metadata'     => ['event_type' => $eventType, ...$data],
                ]);

                BroadcastChatMessage::dispatch($groupMessage);
            }
        }

        // Laravel notification (DB + broadcast + web push)
        $recipients = $users ?? User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))->get();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new SystemNotification(
                    tenantId:  $tenantId,
                    title:     $title,
                    body:      $body,
                    eventType: $eventType,
                    data:      $data,
                )
            );
        }
    }

    /**
     * Post a plain text message as a specific user to every group room in the tenant.
     * Use this when a user action (e.g. creating an invoice) should appear as their
     * own message in the chat, with optional metadata for rendering extra UI (e.g. a download button).
     */
    public static function postAsUser(
        string $tenantId,
        int    $userId,
        string $body,
        array  $metadata = [],
    ): void {
        $groupRooms = ChatRoom::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('type', 'group')
            ->get();

        foreach ($groupRooms as $room) {
            $message = ChatMessage::create([
                'tenant_id'    => $tenantId,
                'chat_room_id' => $room->id,
                'user_id'      => $userId,
                'type'         => 'text',
                'body'         => $body,
                'metadata'     => $metadata,
            ]);

            BroadcastChatMessage::dispatch($message);
        }
    }
}
