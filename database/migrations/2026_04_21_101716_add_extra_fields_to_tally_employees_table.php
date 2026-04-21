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
        Schema::table('tally_employees', function (Blueprint $table) {
            $table->string('location')->nullable()->after('department');
            $table->string('father_name')->nullable()->after('gender');
            $table->string('spouse_name')->nullable()->after('father_name');
            $table->json('aliases')->nullable()->after('salary_details');
        });
    }

    public function down(): void
    {
        Schema::table('tally_employees', function (Blueprint $table) {
            $table->dropColumn(['location', 'father_name', 'spouse_name', 'aliases']);
        });
    }
};
