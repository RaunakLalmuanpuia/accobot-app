<?php

namespace App\Http\Controllers;

use App\Events\BroadcastChatMessage;
use App\Events\BroadcastReadReceipt;
use App\Events\BroadcastTyping;
use App\Models\AuditEvent;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\MessageRead;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    public function index(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $query = ChatMessage::where('chat_room_id', $room->id)
            ->with(['sender:id,name', 'attachments', 'reactions', 'replyTo.sender:id,name']);

        if ($beforeId = $request->query('before_id')) {
            $ref = ChatMessage::find($beforeId);
            if ($ref) {
                $query->where('created_at', '<', $ref->created_at);
            }
        }

        $messages = $query->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $data = $request->validate([
            'body'                => 'nullable|string|max:4000',
            'reply_to_message_id' => 'nullable|uuid',
            'attachment_ids'      => 'nullable|array|max:5',
            'attachment_ids.*'    => 'uuid',
        ]);

        abort_if(
            empty($data['body']) && empty($data['attachment_ids']),
            422,
            'A message must have a body or at least one attachment.'
        );

        // Validate reply_to belongs to this room
        if (! empty($data['reply_to_message_id'])) {
            abort_unless(
                ChatMessage::where('id', $data['reply_to_message_id'])
                    ->where('chat_room_id', $room->id)
                    ->exists(),
                422,
                'Invalid reply target.'
            );
        }

        $message = ChatMessage::create([
            'tenant_id'           => $tenant->id,
            'chat_room_id'        => $room->id,
            'user_id'             => auth()->id(),
            'body'                => $data['body'] ?? null,
            'type'                => 'text',
            'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
        ]);

        // Link pre-uploaded attachments to this message
        if (! empty($data['attachment_ids'])) {
            ChatAttachment::whereIn('id', $data['attachment_ids'])
                ->where('user_id', auth()->id())
                ->whereNull('chat_message_id')
                ->update(['chat_message_id' => $message->id]);
        }

        BroadcastChatMessage::dispatch($message);

        AuditEvent::log('chat.message.sent', [
            'room_id'    => $room->id,
            'message_id' => $message->id,
        ], tenantId: $tenant->id);

        $message->load(['sender:id,name', 'attachments', 'reactions']);

        return response()->json(['data' => $message], 201);
    }

    public function destroy(Tenant $tenant, ChatRoom $room, ChatMessage $message): JsonResponse
    {
        $isOwner    = $message->user_id === auth()->id();
        $canDelete  = auth()->user()->hasPermissionInTenant('chat.message.delete', $tenant);

        abort_unless($isOwner || $canDelete, 403);

        AuditEvent::log('chat.message.deleted', [
            'room_id'    => $room->id,
            'message_id' => $message->id,
        ], tenantId: $tenant->id);

        // Wipe body before soft-delete
        $message->body = null;
        $message->save();
        $message->delete();

        BroadcastChatMessage::dispatch($message->fresh());

        return response()->json(['ok' => true]);
    }

    public function typing(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $data = $request->validate(['typing' => 'required|boolean']);

        BroadcastTyping::dispatch(
            $tenant->id,
            $room->id,
            auth()->id(),
            auth()->user()->name,
            $data['typing'],
        );

        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $data = $request->validate(['message_id' => 'required|uuid']);

        $read = MessageRead::updateOrCreate(
            ['chat_room_id' => $room->id, 'user_id' => auth()->id()],
            [
                'tenant_id'            => $tenant->id,
                'last_read_message_id' => $data['message_id'],
                'read_at'              => now(),
            ]
        );

        // Also keep the member pivot in sync
        ChatRoomMember::where('chat_room_id', $room->id)
            ->where('user_id', auth()->id())
            ->update(['last_read_message_id' => $data['message_id']]);

        BroadcastReadReceipt::dispatch($read);

        return response()->json(['ok' => true]);
    }
}
