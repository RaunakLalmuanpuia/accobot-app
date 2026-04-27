<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            // Nullable until linked to a message after send
            $table->foreignUuid('chat_message_id')->nullable()->constrained('chat_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 20)->default('local');
            $table->string('path', 1000);
            $table->string('original_filename');
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->index('chat_message_id', 'chat_attachments_message_id_index');
            $table->index('tenant_id', 'chat_attachments_tenant_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_attachments');
    }
};
