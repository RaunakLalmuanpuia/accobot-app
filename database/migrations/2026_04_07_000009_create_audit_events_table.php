<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('occurred_at')->useCurrent();

            $table->uuid('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('actor_type')->default('human');             // human | integration | system
            $table->unsignedBigInteger('impersonator_user_id')->nullable();

            $table->string('event_type')->index();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('metadata')->nullable();

            // Append-only: no updated_at, no soft deletes
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
