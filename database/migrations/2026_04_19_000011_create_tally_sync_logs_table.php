<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entity');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->boolean('triggered_manually')->default(false);
            $table->integer('records_created')->default(0);
            $table->integer('records_updated')->default(0);
            $table->integer('records_skipped')->default(0);
            $table->integer('records_failed')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'entity', 'direction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_sync_logs');
    }
};
