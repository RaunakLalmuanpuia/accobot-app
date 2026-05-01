<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->jsonb('part_nos')->nullable()->after('aliases');
        });
    }

    public function down(): void
    {
        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->dropColumn('part_nos');
        });
    }
};
