<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('chat_message_id')->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 10);
            $table->timestamps();

            $table->unique(['chat_message_id', 'user_id', 'emoji']);
            $table->index('tenant_id', 'message_reactions_tenant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};
