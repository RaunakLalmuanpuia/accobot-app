<?php

namespace App\Console\Commands;

use App\Models\ChatAttachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanOrphanChatAttachments extends Command
{
    protected $signature   = 'chat:clean-orphan-attachments';
    protected $description = 'Delete chat attachments that were uploaded but never linked to a message.';

    public function handle(): int
    {
        $orphans = ChatAttachment::whereNull('chat_message_id')
            ->where('created_at', '<', now()->subHour())
            ->get();

        $deleted = 0;

        foreach ($orphans as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
            $attachment->delete();
            $deleted++;
        }

        $this->info("Deleted {$deleted} orphan chat attachment(s).");

        return self::SUCCESS;
    }
}
