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
        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->string('guid')->nullable()->after('tally_id');
            $table->string('cost_centre_category')->nullable()->after('parent_name');
        });
    }

    public function down(): void
    {
        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->dropColumn(['guid', 'cost_centre_category']);
        });
    }
};
