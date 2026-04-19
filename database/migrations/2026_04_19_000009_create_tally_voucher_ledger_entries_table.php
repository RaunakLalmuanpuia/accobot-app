<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_voucher_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tally_voucher_id')->constrained('tally_vouchers')->cascadeOnDelete();
            $table->foreignId('tally_ledger_id')->nullable()->constrained('tally_ledgers')->nullOnDelete();
            $table->string('ledger_name')->nullable();
            $table->string('ledger_group')->nullable();
            $table->decimal('ledger_amount', 15, 2)->default(0);
            $table->boolean('is_deemed_positive')->default(false);
            $table->boolean('is_party_ledger')->default(false);
            $table->string('igst_rate')->nullable();
            $table->string('hsn_code')->nullable();
            $table->string('cess_rate')->nullable();
            $table->jsonb('bills_allocation')->nullable();
            $table->timestamps();

            $table->index('tally_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_voucher_ledger_entries');
    }
};
