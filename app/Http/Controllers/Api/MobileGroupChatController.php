<?php

namespace App\Http\Controllers\Api;

use App\Events\BroadcastChatMessage;
use App\Events\BroadcastReaction;
use App\Events\BroadcastReadReceipt;
use App\Events\BroadcastTyping;
use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\MessageReaction;
use App\Models\MessageRead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileGroupChatController extends Controller
{
    private function assertMember(ChatRoom $room): void
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );
    }

    private function appendDownloadUrls(\Illuminate\Support\Collection $messages, Tenant $tenant, ChatRoom $room): void
    {
        foreach ($messages as $message) {
            foreach ($message->attachments as $attachment) {
                $attachment->download_url = route('api.mobile.tenant.groups.attachments.download', [
                    'tenant'     => $tenant->id,
                    'room'       => $room->id,
                    'attachment' => $attachment->id,
                ]);
            }
        }
    }

    /**
     * GET /api/mobile/tenants/{tenant}/groups
     */
    public function rooms(Tenant $tenant): JsonResponse
    {
        $userId = auth()->id();

        $rooms = ChatRoom::where('tenant_id', $tenant->id)
            ->forUser($userId)
            ->with(['latestMessage.sender:id,name', 'members.user:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        $memberMap = ChatRoomMember::whereIn('chat_room_id', $rooms->pluck('id'))
            ->where('user_id', $userId)
            ->pluck('last_read_message_id', 'chat_room_id');

        $lastReadMessages = ChatMessage::whereIn('id', $memberMap->filter()->values())
            ->pluck('created_at', 'id');

        $rooms = $rooms->map(function (ChatRoom $room) use ($memberMap, $lastReadMessages) {
            $lastReadId = $memberMap[$room->id] ?? null;
            $cutoff     = $lastReadId ? ($lastReadMessages[$lastReadId] ?? null) : null;

            $room->unread_count = ChatMessage::where('chat_room_id', $room->id)
                ->when($cutoff, fn ($q) => $q->where('created_at', '>', $cutoff))
                ->count();

            return $room;
        });

        $tenantUsers = $tenant->users()
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'data'         => $rooms,
            'tenant_users' => $tenantUsers,
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/groups
     */
    public function createRoom(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'member_ids'   => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        if (! empty($data['member_ids'])) {
            $validCount = $tenant->users()->whereIn('users.id', $data['member_ids'])->count();
            abort_if($validCount !== count($data['member_ids']), 422, 'One or more members do not belong to this tenant.');
        }

        $room = ChatRoom::create([
            'tenant_id'          => $tenant->id,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'type'               => 'group',
            'created_by_user_id' => auth()->id(),
        ]);

        ChatRoomMember::create([
            'tenant_id'    => $tenant->id,
            'chat_room_id' => $room->id,
            'user_id'      => auth()->id(),
            'role'         => 'admin',
            'joined_at'    => now(),
        ]);

        foreach ($data['member_ids'] ?? [] as $memberId) {
            if ($memberId === auth()->id()) continue;
            ChatRoomMember::firstOrCreate(
                ['chat_room_id' => $room->id, 'user_id' => $memberId],
                ['tenant_id' => $tenant->id, 'role' => 'member', 'joined_at' => now()]
            );
        }

        AuditEvent::log('chat.room.created', [
            'room_id'   => $room->id,
            'room_name' => $room->name,
        ], tenantId: $tenant->id);

        return response()->json(['data' => $room->load('members.user:id,name')], 201);
    }

    /**
     * GET /api/mobile/tenants/{tenant}/groups/{room}
     */
    public function showRoom(Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $this->assertMember($room);

        $messages = ChatMessage::where('chat_room_id', $room->id)
            ->with(['sender:id,name', 'attachments', 'reactions', 'replyTo.sender:id,name'])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $this->appendDownloadUrls($messages, $tenant, $room);

        $tenantUsers = $tenant->users()
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'room'          => $room->load('members.user:id,name'),
            'tenant_users'  => $tenantUsers,
            'messages'      => $messages,
            'can_load_more' => $messages->count() === 50,
        ]);
    }

    /**
     * GET /api/mobile/tenants/{tenant}/groups/{room}/messages?before_id=
     */
    public function messages(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $this->assertMember($room);

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

        $this->appendDownloadUrls($messages, $tenant, $room);

        return response()->json([
            'data'          => $messages,
            'can_load_more' => $messages->count() === 50,
        ]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/groups/{room}/messages
     */
    public function sendMessage(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $this->assertMember($room);

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

        $message->load(['sender:id,name', 'attachments', 'reactions', 'replyTo.sender:id,name']);

        $this->appendDownloadUrls(collect([$message]), $tenant, $room);

        return response()->json(['data' => $message], 201);
    }

    /**
     * DELETE /api/mobile/tenants/{tenant}/groups/{room}/messages/{message}
     */
    public function deleteMessage(Tenant $tenant, ChatRoom $room, ChatMessage $message): JsonResponse
    {
        $isOwner   = $message->user_id === auth()->id();
        $canDelete = auth()->user()->hasPermissionInTenant('chat.message.delete', $tenant);

        abort_unless($isOwner || $canDelete, 403);

        AuditEvent::log('chat.message.deleted', [
            'room_id'    => $room->id,
            'message_id' => $message->id,
        ], tenantId: $tenant->id);

        $message->body = null;
        $message->save();
        $message->delete();

        BroadcastChatMessage::dispatch($message->fresh());

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/groups/{room}/messages/{message}/reactions
     */
    public function toggleReaction(Request $request, Tenant $tenant, ChatRoom $room, ChatMessage $message): JsonResponse
    {
        $this->assertMember($room);

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

        $message->load('reactions');

        return response()->json(['reaction_summary' => $message->reaction_summary]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/groups/{room}/read
     */
    public function markRead(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $this->assertMember($room);

        $data = $request->validate(['message_id' => 'required|uuid']);

        $read = MessageRead::updateOrCreate(
            ['chat_room_id' => $room->id, 'user_id' => auth()->id()],
            [
                'tenant_id'            => $tenant->id,
                'last_read_message_id' => $data['message_id'],
                'read_at'              => now(),
            ]
        );

        ChatRoomMember::where('chat_room_id', $room->id)
            ->where('user_id', auth()->id())
            ->update(['last_read_message_id' => $data['message_id']]);

        BroadcastReadReceipt::dispatch($read);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/groups/{room}/typing
     */
    public function typing(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $this->assertMember($room);

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

    /**
     * POST /api/mobile/tenants/{tenant}/groups/{room}/attachments
     */
    public function uploadAttachment(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $this->assertMember($room);

        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv'],
        ]);

        $file      = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $path      = 'chat/' . $tenant->id . '/' . now()->format('Y_m') . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('local')->putFileAs('', $file, $path);

        $attachment = ChatAttachment::create([
            'tenant_id'         => $tenant->id,
            'chat_message_id'   => null,
            'user_id'           => auth()->id(),
            'disk'              => 'local',
            'path'              => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'size_bytes'        => $file->getSize(),
        ]);

        return response()->json(['data' => $attachment], 201);
    }

    /**
     * GET /api/mobile/tenants/{tenant}/groups/{room}/attachments/{attachment}
     */
    public function downloadAttachment(Tenant $tenant, ChatRoom $room, ChatAttachment $attachment): StreamedResponse
    {
        $this->assertMember($room);

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_filename);
    }

    /**
     * POST /api/mobile/tenants/{tenant}/groups/{room}/members
     */
    public function addMember(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role'    => 'nullable|in:admin,member',
        ]);

        abort_if(
            ! $tenant->users()->where('users.id', $data['user_id'])->exists(),
            422,
            'User does not belong to this tenant.'
        );

        ChatRoomMember::firstOrCreate(
            ['chat_room_id' => $room->id, 'user_id' => $data['user_id']],
            ['tenant_id' => $tenant->id, 'role' => $data['role'] ?? 'member', 'joined_at' => now()]
        );

        AuditEvent::log('chat.room.member_added', [
            'room_id' => $room->id,
            'user_id' => $data['user_id'],
        ], tenantId: $tenant->id);

        return response()->json(['ok' => true]);
    }

    /**
     * DELETE /api/mobile/tenants/{tenant}/groups/{room}/members/{user}
     */
    public function removeMember(Tenant $tenant, ChatRoom $room, User $user): JsonResponse
    {
        ChatRoomMember::where('chat_room_id', $room->id)
            ->where('user_id', $user->id)
            ->delete();

        AuditEvent::log('chat.room.member_removed', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ], tenantId: $tenant->id);

        return response()->json(['ok' => true]);
    }
}
