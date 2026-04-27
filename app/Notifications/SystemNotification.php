<?php

namespace App\Notifications;

use App\Events\BroadcastSystemNotification;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string  $tenantId,
        public string  $title,
        public string  $body,
        public string  $eventType,
        public array   $data = [],
        public ?int    $targetUserId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'tenant_id'  => $this->tenantId,
            'title'      => $this->title,
            'body'       => $this->body,
            'event_type' => $this->eventType,
            'data'       => $this->data,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'tenant_id'  => $this->tenantId,
            'title'      => $this->title,
            'body'       => $this->body,
            'event_type' => $this->eventType,
            'data'       => $this->data,
            'user_id'    => $this->targetUserId,
        ]);
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'data'  => [
                'event_type' => $this->eventType,
                'url'        => '/t/' . $this->tenantId . '/groups',
            ],
        ];
    }
}
