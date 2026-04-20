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
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->foreignId('mapped_vendor_id')->nullable()->constrained('vendors')->nullOnDelete()->after('mapped_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('tally_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mapped_vendor_id');
        });
    }
};
