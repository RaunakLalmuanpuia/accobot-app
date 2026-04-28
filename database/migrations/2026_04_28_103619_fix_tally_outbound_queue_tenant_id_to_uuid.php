<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing rows have corrupt tenant_id values (PHP int-cast of UUID gives leading digits only).
        // Truncate before altering — no valid data to preserve.
        DB::table('tally_outbound_queue')->truncate();

        Schema::table('tally_outbound_queue', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'entity_type', 'status']);
            $table->dropUnique(['tenant_id', 'entity_type', 'entity_id']);
        });

        // PostgreSQL requires USING clause for bigint → uuid; table is empty so cast expression is safe.
        DB::statement('ALTER TABLE tally_outbound_queue ALTER COLUMN tenant_id TYPE uuid USING gen_random_uuid()');

        Schema::table('tally_outbound_queue', function (Blueprint $table) {
            $table->unique(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'entity_type', 'status']);
        });
    }

    public function down(): void
    {
        DB::table('tally_outbound_queue')->truncate();

        Schema::table('tally_outbound_queue', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'entity_type', 'status']);
            $table->dropUnique(['tenant_id', 'entity_type', 'entity_id']);
        });

        DB::statement('ALTER TABLE tally_outbound_queue ALTER COLUMN tenant_id TYPE bigint USING 0');

        Schema::table('tally_outbound_queue', function (Blueprint $table) {
            $table->unique(['tenant_id', 'entity_type', 'entity_id']);
            $table->index(['tenant_id', 'entity_type', 'status']);
        });
    }
};
