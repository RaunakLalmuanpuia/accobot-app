<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->string('company_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('inbound_token', 48)->unique();
            $table->timestamp('inbound_token_last_used_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_connections');
    }
};
