<?php

namespace App\Events;

use App\Models\MessageReaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastReaction implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public MessageReaction $reaction,
        public string $action, // 'added' | 'removed'
    ) {
        $this->reaction->load('message:id,chat_room_id');
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("room.{$this->reaction->tenant_id}.{$this->reaction->message->chat_room_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.reaction';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->reaction->chat_message_id,
            'emoji'      => $this->reaction->emoji,
            'user_id'    => $this->reaction->user_id,
            'action'     => $this->action,
        ];
    }
}
