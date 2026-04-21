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
        Schema::table('tally_attendance_types', function (Blueprint $table) {
            $table->string('guid')->nullable()->after('tally_id');
            $table->string('under')->nullable()->after('attendance_type');
        });
    }

    public function down(): void
    {
        Schema::table('tally_attendance_types', function (Blueprint $table) {
            $table->dropColumn(['guid', 'under']);
        });
    }
};
