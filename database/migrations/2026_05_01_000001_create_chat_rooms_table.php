<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type', 20)->default('group'); // group | notifications
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'chat_rooms_tenant_id_index');
            $table->index(['tenant_id', 'type'], 'chat_rooms_tenant_id_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_rooms');
    }
};
