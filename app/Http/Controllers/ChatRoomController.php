<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatRoomController extends Controller
{
    public function index(Tenant $tenant): Response
    {
        $userId = auth()->id();

        $rooms = ChatRoom::where('tenant_id', $tenant->id)
            ->forUser($userId)
            ->with([
                'latestMessage.sender:id,name',
                'members.user:id,name',
            ])
            ->orderByDesc('updated_at')
            ->get();

        // Bulk-fetch member rows for unread count calculation
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

        return Inertia::render('Chat/Groups/Index', [
            'tenant'      => $tenant,
            'rooms'       => $rooms,
            'tenantUsers' => $tenantUsers,
        ]);
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'member_ids'  => 'nullable|array',
            'member_ids.*' => 'integer|exists:users,id',
        ]);

        // Ensure all invited members belong to this tenant
        if (! empty($data['member_ids'])) {
            $validCount = $tenant->users()->whereIn('users.id', $data['member_ids'])->count();
            abort_if($validCount !== count($data['member_ids']), 422, 'One or more members do not belong to this tenant.');
        }

        $room = ChatRoom::create([
            'tenant_id'         => $tenant->id,
            'name'              => $data['name'],
            'description'       => $data['description'] ?? null,
            'type'              => 'group',
            'created_by_user_id' => auth()->id(),
        ]);

        // Creator as admin
        ChatRoomMember::create([
            'tenant_id'    => $tenant->id,
            'chat_room_id' => $room->id,
            'user_id'      => auth()->id(),
            'role'         => 'admin',
            'joined_at'    => now(),
        ]);

        // Additional members
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

        return redirect()->route('chat.groups.show', [$tenant, $room]);
    }

    public function show(Tenant $tenant, ChatRoom $room): Response
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $messages = ChatMessage::withTrashed()->where('chat_room_id', $room->id)
            ->with([
                'sender:id,name',
                'attachments',
                'reactions',
                'replyTo.sender:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        foreach ($messages as $message) {
            foreach ($message->attachments as $attachment) {
                $attachment->download_url = route('chat.attachments.download', [
                    'tenant'     => $tenant->id,
                    'room'       => $room->id,
                    'attachment' => $attachment->id,
                ]);
            }
        }

        $rooms = ChatRoom::where('tenant_id', $tenant->id)
            ->forUser(auth()->id())
            ->with('latestMessage.sender:id,name')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'type', 'is_system', 'updated_at']);

        $memberIds = $room->members()->pluck('user_id');

        $tenantUsers = $tenant->users()
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('Chat/Groups/Show', [
            'tenant'      => $tenant,
            'room'        => $room->load('members.user:id,name'),
            'messages'    => $messages,
            'rooms'       => $rooms,
            'tenantUsers' => $tenantUsers,
        ]);
    }

    public function update(Request $request, Tenant $tenant, ChatRoom $room): RedirectResponse
    {
        abort_if($room->is_system, 403, 'System rooms cannot be renamed.');

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $room->update($data);

        AuditEvent::log('chat.room.updated', [
            'room_id'   => $room->id,
            'room_name' => $room->name,
        ], tenantId: $tenant->id);

        return back();
    }

    public function destroy(Tenant $tenant, ChatRoom $room): RedirectResponse
    {
        abort_if($room->is_system, 403, 'System rooms cannot be deleted.');

        AuditEvent::log('chat.room.deleted', [
            'room_id'   => $room->id,
            'room_name' => $room->name,
        ], tenantId: $tenant->id);

        $room->delete();

        return redirect()->route('chat.groups.index', $tenant);
    }

    public function addMember(Request $request, Tenant $tenant, ChatRoom $room): RedirectResponse
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

        return back();
    }

    public function removeMember(Tenant $tenant, ChatRoom $room, User $user): RedirectResponse
    {
        ChatRoomMember::where('chat_room_id', $room->id)
            ->where('user_id', $user->id)
            ->delete();

        AuditEvent::log('chat.room.member_removed', [
            'room_id' => $room->id,
            'user_id' => $user->id,
        ], tenantId: $tenant->id);

        return back();
    }
}
