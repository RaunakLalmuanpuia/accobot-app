<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_stock_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');

            // Identity
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->jsonb('aliases')->nullable();

            // Classification
            $table->integer('stock_group_id')->nullable();
            $table->string('stock_group_name')->nullable();
            $table->integer('stock_category_id')->nullable();
            $table->string('category_name')->nullable();

            // Units
            $table->integer('unit_id')->nullable();
            $table->string('unit_name')->nullable();
            $table->string('alternate_unit')->nullable();
            $table->decimal('conversion', 10, 4)->nullable();
            $table->integer('denominator')->default(1);

            // GST & Tax
            $table->boolean('is_gst_applicable')->default(false);
            $table->string('taxability')->nullable();
            $table->string('calculation_type')->nullable();
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('cess_rate', 5, 2)->default(0);
            $table->string('hsn_code')->nullable();

            // Pricing
            $table->decimal('mrp_rate', 12, 2)->nullable();
            $table->decimal('standard_cost', 12, 2)->nullable();
            $table->decimal('standard_price', 12, 2)->nullable();

            // Stock Levels
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->decimal('opening_rate', 12, 2)->default(0);
            $table->decimal('opening_value', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 4)->default(0);
            $table->decimal('closing_rate', 12, 2)->default(0);
            $table->decimal('closing_value', 15, 2)->default(0);

            // Inventory Behaviour
            $table->string('costing_method')->nullable();
            $table->boolean('is_batch_applicable')->default(false);
            $table->boolean('is_expiry_date_applicable')->default(false);
            $table->decimal('reorder_level', 15, 4)->nullable();
            $table->decimal('reorder_quantity', 15, 4)->nullable();
            $table->decimal('maximum_quantity', 15, 4)->nullable();

            // Batch / Godown
            $table->jsonb('batch_allocations')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();

            // Accobot mapping
            $table->foreignId('mapped_product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_stock_items');
    }
};
