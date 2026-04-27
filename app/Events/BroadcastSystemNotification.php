<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastSystemNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tenantId,
        public array $payload,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("private-tenant.{$this->tenantId}.notifications"),
        ];

        // Also notify the specific user's private channel if targeted
        if (! empty($this->payload['user_id'])) {
            $channels[] = new PrivateChannel("private-user.{$this->payload['user_id']}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'system.notification';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
