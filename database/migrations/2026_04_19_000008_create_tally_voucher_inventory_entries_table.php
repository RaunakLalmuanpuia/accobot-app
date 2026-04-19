<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_voucher_inventory_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('tally_voucher_id')->constrained('tally_vouchers')->cascadeOnDelete();
            $table->foreignId('tally_stock_item_id')->nullable()->constrained('tally_stock_items')->nullOnDelete();
            $table->string('stock_item_name')->nullable();
            $table->string('item_code')->nullable();
            $table->string('group_name')->nullable();
            $table->string('hsn_code')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('igst_rate', 5, 2)->nullable();
            $table->decimal('cess_rate', 5, 2)->nullable();
            $table->boolean('is_deemed_positive')->default(false);
            $table->decimal('actual_qty', 15, 4)->default(0);
            $table->decimal('billed_qty', 15, 4)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('mrp', 12, 2)->nullable();
            $table->string('sales_ledger')->nullable();
            $table->string('godown_name')->nullable();
            $table->string('batch_name')->nullable();
            $table->jsonb('batch_allocations')->nullable();
            $table->jsonb('accounting_allocations')->nullable();
            $table->timestamps();

            $table->index('tally_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_voucher_inventory_entries');
    }
};
