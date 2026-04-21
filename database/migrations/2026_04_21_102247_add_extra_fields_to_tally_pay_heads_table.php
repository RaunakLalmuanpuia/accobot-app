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
        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->string('income_type')->nullable()->after('pay_head_type');
            $table->string('leave_type')->nullable()->after('calculation_type');
        });
    }

    public function down(): void
    {
        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->dropColumn(['income_type', 'leave_type']);
        });
    }
};
