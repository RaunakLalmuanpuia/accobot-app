<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('tally_stock_item_id')->nullable()->constrained('tally_stock_items')->nullOnDelete();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('tally_ledger_id')->nullable()->constrained('tally_ledgers')->nullOnDelete();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->foreignId('tally_ledger_id')->nullable()->constrained('tally_ledgers')->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('tally_voucher_id')->nullable()->constrained('tally_vouchers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TallyStockItem::class);
            $table->dropColumn('tally_stock_item_id');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TallyLedger::class);
            $table->dropColumn('tally_ledger_id');
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TallyLedger::class);
            $table->dropColumn('tally_ledger_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\TallyVoucher::class);
            $table->dropColumn('tally_voucher_id');
        });
    }
};
