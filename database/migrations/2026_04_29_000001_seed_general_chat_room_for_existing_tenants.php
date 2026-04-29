<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const QUALIFYING_ROLES = ['owner', 'TenantAdmin', 'ExternalAccountant', 'CAManager'];

    public function up(): void
    {
        $tenants = DB::table('tenants')->get(['id']);

        foreach ($tenants as $tenant) {
            // Create the General room if it doesn't already exist
            $existing = DB::table('chat_rooms')
                ->where('tenant_id', $tenant->id)
                ->where('name', 'General')
                ->where('is_system', true)
                ->first();

            if ($existing) {
                $roomId = $existing->id;
            } else {
                $roomId = (string) Str::uuid();
                DB::table('chat_rooms')->insert([
                    'id'         => $roomId,
                    'tenant_id'  => $tenant->id,
                    'name'       => 'General',
                    'type'       => 'group',
                    'is_system'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Find qualifying role IDs
            $roleIds = DB::table('roles')
                ->whereIn('name', self::QUALIFYING_ROLES)
                ->pluck('id');

            // Find all users in this tenant with a qualifying role
            $qualifiedUserIds = DB::table('tenant_user_roles')
                ->where('tenant_id', $tenant->id)
                ->whereIn('role_id', $roleIds)
                ->pluck('user_id');

            foreach ($qualifiedUserIds as $userId) {
                $alreadyMember = DB::table('chat_room_members')
                    ->where('chat_room_id', $roomId)
                    ->where('user_id', $userId)
                    ->exists();

                if (! $alreadyMember) {
                    DB::table('chat_room_members')->insert([
                        'id'           => (string) Str::uuid(),
                        'tenant_id'    => $tenant->id,
                        'chat_room_id' => $roomId,
                        'user_id'      => $userId,
                        'role'         => 'member',
                        'joined_at'    => now(),
                        'created_at'   => now(),
                        'updated_at'   => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('chat_rooms')
            ->where('name', 'General')
            ->where('is_system', true)
            ->delete();
    }
};
