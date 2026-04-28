<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_units', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id')->nullable();
            $table->integer('alter_id')->default(0);
            $table->string('action')->default('Create');
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->string('formal_name')->nullable();
            $table->integer('decimal_places')->default(0);
            $table->string('uqc')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_units');
    }
};
