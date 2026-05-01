<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate (tenant_id, name) rows, keeping the highest id per group.
        DB::statement('
            DELETE FROM tally_units
            WHERE id NOT IN (
                SELECT MAX(id)
                FROM tally_units
                GROUP BY tenant_id, name
            )
        ');

        Schema::table('tally_units', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name'], 'tally_units_tenant_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tally_units', function (Blueprint $table) {
            $table->dropUnique('tally_units_tenant_name_unique');
        });
    }
};
