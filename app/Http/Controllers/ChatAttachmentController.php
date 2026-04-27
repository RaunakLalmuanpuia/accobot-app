<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function store(Request $request, Tenant $tenant, ChatRoom $room): JsonResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,csv',
            ],
        ]);

        $file      = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $path      = 'chat/' . $tenant->id . '/' . now()->format('Y_m') . '/' . Str::uuid() . '.' . $extension;

        Storage::disk('local')->putFileAs('', $file, $path);

        $attachment = ChatAttachment::create([
            'tenant_id'         => $tenant->id,
            'chat_message_id'   => null, // linked when message is sent
            'user_id'           => auth()->id(),
            'disk'              => 'local',
            'path'              => $path,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type'         => $file->getMimeType(),
            'size_bytes'        => $file->getSize(),
        ]);

        return response()->json(['data' => $attachment], 201);
    }

    public function download(Tenant $tenant, ChatRoom $room, ChatAttachment $attachment): StreamedResponse
    {
        abort_unless(
            ChatRoomMember::where('chat_room_id', $room->id)->where('user_id', auth()->id())->exists(),
            403
        );

        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_filename);
    }
}
