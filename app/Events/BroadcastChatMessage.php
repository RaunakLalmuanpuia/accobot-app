<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public ChatMessage $message)
    {
        $this->message->load(['sender:id,name', 'attachments', 'reactions', 'replyTo.sender:id,name']);
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("room.{$this->message->tenant_id}.{$this->message->chat_room_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'id'                  => $this->message->id,
            'chat_room_id'        => $this->message->chat_room_id,
            'user_id'             => $this->message->user_id,
            'sender_name'         => $this->message->sender?->name,
            'body'                => $this->message->body,
            'type'                => $this->message->type,
            'metadata'            => $this->message->metadata,
            'reply_to_message_id' => $this->message->reply_to_message_id,
            'reply_to'            => $this->message->replyTo ? [
                'id'          => $this->message->replyTo->id,
                'body'        => $this->message->replyTo->body,
                'sender_name' => $this->message->replyTo->sender?->name,
            ] : null,
            'attachments'         => $this->message->attachments->map(fn ($att) => array_merge($att->toArray(), [
                'download_url' => route('chat.attachments.download', [
                    'tenant'     => $this->message->tenant_id,
                    'room'       => $this->message->chat_room_id,
                    'attachment' => $att->id,
                ]),
            ]))->toArray(),
            'reactions'           => $this->message->reaction_summary,
            'edited_at'           => $this->message->edited_at?->toISOString(),
            'deleted_at'          => $this->message->deleted_at?->toISOString(),
            'created_at'          => $this->message->created_at->toISOString(),
        ];
    }
}
