<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_ledger_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');
            $table->string('name');
            $table->integer('under_id')->nullable();
            $table->string('under_name')->nullable();
            $table->string('nature_of_group')->nullable();
            $table->boolean('is_revenue')->nullable();
            $table->boolean('affects_gross')->nullable();
            $table->boolean('is_addable')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_ledger_groups');
    }
};
