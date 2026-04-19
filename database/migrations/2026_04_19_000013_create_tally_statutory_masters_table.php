<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_statutory_masters', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');
            $table->string('name');
            $table->string('statutory_type')->nullable(); // GST, TDS, TCS, VAT, IT
            $table->string('registration_number')->nullable();
            $table->string('state_code')->nullable();
            $table->string('registration_type')->nullable();
            $table->string('pan')->nullable();
            $table->string('tan')->nullable();
            $table->date('applicable_from')->nullable();
            $table->json('details')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_statutory_masters');
    }
};
