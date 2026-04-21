<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_attendance_types', function (Blueprint $table) {
            $table->renameColumn('unit_of_measure', 'attendance_period');
        });

        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->renameColumn('rate_period', 'calculation_period');
        });
    }

    public function down(): void
    {
        Schema::table('tally_attendance_types', function (Blueprint $table) {
            $table->renameColumn('attendance_period', 'unit_of_measure');
        });

        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->renameColumn('calculation_period', 'rate_period');
        });
    }
};
