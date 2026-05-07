<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->jsonb('aliases')->nullable()->after('cost_centre_category');
        });
    }

    public function down(): void
    {
        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
