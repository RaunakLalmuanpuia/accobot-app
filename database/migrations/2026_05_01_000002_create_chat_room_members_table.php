<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_room_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('member'); // admin | member
            $table->timestamp('joined_at')->useCurrent();
            // Application-level FK only — no DB constraint to avoid circular dependency with chat_messages
            $table->uuid('last_read_message_id')->nullable();
            $table->timestamps();

            $table->unique(['chat_room_id', 'user_id']);
            $table->index('tenant_id', 'chat_room_members_tenant_id_index');
            $table->index('user_id', 'chat_room_members_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_room_members');
    }
};
