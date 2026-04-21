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
        Schema::table('tally_stock_groups', function (Blueprint $table) {
            $table->jsonb('aliases')->nullable()->after('parent_name');
        });

        Schema::table('tally_stock_categories', function (Blueprint $table) {
            $table->jsonb('aliases')->nullable()->after('parent_name');
        });
    }

    public function down(): void
    {
        Schema::table('tally_stock_groups', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });

        Schema::table('tally_stock_categories', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
