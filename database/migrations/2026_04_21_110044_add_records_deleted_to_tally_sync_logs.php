<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tally_sync_logs', function (Blueprint $table) {
            $table->unsignedInteger('records_deleted')->default(0)->after('records_skipped');
        });
    }

    public function down(): void
    {
        Schema::table('tally_sync_logs', function (Blueprint $table) {
            $table->dropColumn('records_deleted');
        });
    }
};
