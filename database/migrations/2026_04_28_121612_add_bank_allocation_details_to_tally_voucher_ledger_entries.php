<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_voucher_ledger_entries', function (Blueprint $table) {
            $table->jsonb('bank_allocation_details')->nullable()->after('bills_allocation');
        });
    }

    public function down(): void
    {
        Schema::table('tally_voucher_ledger_entries', function (Blueprint $table) {
            $table->dropColumn('bank_allocation_details');
        });
    }
};
