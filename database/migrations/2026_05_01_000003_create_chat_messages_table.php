<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('chat_room_id')->constrained('chat_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body')->nullable();
            $table->string('type', 20)->default('text'); // text | system | attachment
            $table->jsonb('metadata')->nullable();
            // Application-level FK only — self-referencing FKs in the same migration cause PostgreSQL issues
            $table->uuid('reply_to_message_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'chat_messages_tenant_id_index');
            $table->index(['chat_room_id', 'created_at'], 'chat_messages_room_created_index');
            $table->index('user_id', 'chat_messages_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
