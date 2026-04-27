<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // Application-level FK — high-water mark, one row per user per room
            $table->uuid('last_read_message_id');
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['chat_room_id', 'user_id']);
            $table->index('tenant_id', 'message_reads_tenant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
    }
};
