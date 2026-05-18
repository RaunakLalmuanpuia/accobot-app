<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_godowns', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id')->default(0);
            $table->string('action')->default('Create');
            $table->string('guid')->nullable();

            // Identity
            $table->string('name');
            $table->string('under')->nullable();
            $table->jsonb('aliases')->nullable();

            // Properties
            $table->boolean('has_no_space')->default(false);
            $table->boolean('has_no_stock')->default(false);
            $table->boolean('is_external')->default(false);
            $table->boolean('is_internal')->default(false);
            $table->jsonb('address')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_godowns');
    }
};
