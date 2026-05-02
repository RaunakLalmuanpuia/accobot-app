<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->string('voucher_base_type')->nullable()->after('voucher_type');
        });
    }

    public function down(): void
    {
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->dropColumn('voucher_base_type');
        });
    }
};
