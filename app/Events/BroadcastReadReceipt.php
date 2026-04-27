<?php

namespace App\Events;

use App\Models\MessageRead;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastReadReceipt implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public MessageRead $read) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("presence-room.{$this->read->tenant_id}.{$this->read->chat_room_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.read';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'              => $this->read->user_id,
            'last_read_message_id' => $this->read->last_read_message_id,
        ];
    }
}
