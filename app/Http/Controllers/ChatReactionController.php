<?php

namespace App\Http\Controllers;

use App\Events\BroadcastReaction;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\MessageReaction;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatReactionController extends Controller
{
    public function toggle(Request $request, Tenant $tenant, ChatRoom $room, ChatMessage $message): JsonResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $data = $request->validate(['emoji' => 'required|string|max:10']);

        $existing = MessageReaction::where('chat_message_id', $message->id)
            ->where('user_id', auth()->id())
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing) {
            $existing->delete();
            $action   = 'removed';
            $reaction = $existing;
        } else {
            $reaction = MessageReaction::create([
                'tenant_id'       => $tenant->id,
                'chat_message_id' => $message->id,
                'user_id'         => auth()->id(),
                'emoji'           => $data['emoji'],
            ]);
            $action = 'added';
        }

        BroadcastReaction::dispatch($reaction, $action);

        // Return updated reaction summary for the message
        $message->load('reactions');

        return response()->json(['reaction_summary' => $message->reaction_summary]);
    }
}
