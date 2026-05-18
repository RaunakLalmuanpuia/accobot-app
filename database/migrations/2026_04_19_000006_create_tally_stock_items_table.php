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
            $table->string('guid')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->jsonb('aliases')->nullable();
            $table->jsonb('part_nos')->nullable();

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
            $table->string('reporting_uom')->nullable();
            $table->string('reporting_uom_date')->nullable();

            // GST & Tax
            $table->boolean('is_gst_applicable')->default(false);
            $table->string('taxability')->nullable();
            $table->string('calculation_type')->nullable();
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('cess_rate', 5, 2)->default(0);
            $table->string('hsn_code')->nullable();
            $table->string('hsn_desc')->nullable();
            $table->string('type_of_supply')->nullable();

            // TCS
            $table->string('tcs_applicable')->nullable();
            $table->string('tcs_category')->nullable();

            // Pricing
            $table->decimal('mrp_rate', 12, 2)->nullable();
            $table->boolean('inclusive_tax')->default(false);
            $table->boolean('modify_mrp_rate')->default(false);
            $table->boolean('calc_on_mrp')->default(false);
            $table->boolean('mrp_incl_of_tax')->default(false);
            $table->decimal('basic_rate_of_excise', 10, 4)->nullable();

            // Costing / Valuation
            $table->string('costing_method')->nullable();
            $table->string('valuation_method')->nullable();

            // Default Ledgers
            $table->string('sales_ledger')->nullable();
            $table->decimal('sales_ledger_rate', 5, 2)->nullable();
            $table->string('purchase_ledger')->nullable();
            $table->decimal('purchase_ledger_rate', 5, 2)->nullable();

            // Stock Levels
            $table->decimal('opening_balance', 15, 4)->default(0);
            $table->decimal('opening_rate', 12, 2)->default(0);
            $table->decimal('opening_value', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 4)->default(0);
            $table->decimal('closing_rate', 12, 2)->default(0);
            $table->decimal('closing_value', 15, 2)->default(0);

            // Inventory Behaviour
            $table->boolean('is_batch_wise')->default(false);
            $table->boolean('is_perishable')->default(false);
            $table->boolean('has_mfg_date')->default(false);
            $table->boolean('allow_expired_items')->default(false);
            $table->boolean('ignore_batches')->default(false);
            $table->boolean('ignore_godowns')->default(false);
            $table->boolean('ignore_phys_diff')->default(false);
            $table->boolean('ignore_neg_stock')->default(false);
            $table->boolean('treat_sales_as_mfg')->default(false);
            $table->boolean('treat_purch_consumed')->default(false);
            $table->boolean('treat_rejects_scrap')->default(false);
            $table->boolean('is_cost_centres_on')->default(false);
            $table->boolean('is_cost_tracking_on')->default(false);

            // Legacy VAT
            $table->string('is_entry_tax_applicable')->nullable();
            $table->string('is_rate_inclusive_vat')->nullable();
            $table->string('vat_base_unit')->nullable();

            // Batch / Godown
            $table->jsonb('batch_allocations')->nullable();

            // GST / HSN / VAT structured arrays
            $table->jsonb('gst_details_list')->nullable();
            $table->jsonb('hsn_details_list')->nullable();
            $table->jsonb('vat_details')->nullable();

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
