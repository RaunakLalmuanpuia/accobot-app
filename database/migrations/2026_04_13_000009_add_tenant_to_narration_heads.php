<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Truncate existing global seed data — will be re-seeded per tenant
        DB::statement('TRUNCATE narration_sub_heads, narration_heads RESTART IDENTITY CASCADE');

        Schema::table('narration_heads', function (Blueprint $table) {
            // Remove global unique on slug — uniqueness will be per tenant
            $table->dropUnique(['slug']);

            // Add tenant scope
            $table->foreignUuid('tenant_id')
                ->after('id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            // Slug unique per tenant
            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('narration_heads', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'slug']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique('slug');
        });
    }
};
