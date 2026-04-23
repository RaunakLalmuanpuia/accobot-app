<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_outbound_queue', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->enum('status', ['pending', 'confirmed'])->default('pending');
            $table->timestamp('queued_at')->useCurrent();
            $table->timestamp('confirmed_at')->nullable();

            $table->unique(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'entity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_outbound_queue');
    }
};
