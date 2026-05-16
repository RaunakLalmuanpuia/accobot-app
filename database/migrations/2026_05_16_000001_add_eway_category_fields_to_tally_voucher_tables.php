<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->jsonb('eway_bill_details')->nullable()->after('qr_code');
            $table->jsonb('category_entries')->nullable()->after('eway_bill_details');
        });

        Schema::table('tally_voucher_ledger_entries', function (Blueprint $table) {
            $table->jsonb('category_allocation')->nullable()->after('bank_allocation_details');
        });
    }

    public function down(): void
    {
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->dropColumn(['eway_bill_details', 'category_entries']);
        });

        Schema::table('tally_voucher_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('category_allocation');
        });
    }
};
