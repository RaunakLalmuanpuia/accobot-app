<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_stock_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');

            // Identity
            $table->string('name');
            $table->string('parent_name')->nullable();
            $table->jsonb('aliases')->nullable();

            // Inventory Behaviour (cascades to items in this group)
            $table->string('costing_method')->nullable();
            $table->string('valuation_method')->nullable();
            $table->boolean('is_batch_wise_on')->default(false);
            $table->boolean('is_perishable_on')->default(false);
            $table->boolean('is_addable')->default(false);
            $table->boolean('ignore_phys_diff')->default(false);
            $table->boolean('ignore_neg_stock')->default(false);
            $table->boolean('treat_sales_as_mfg')->default(false);
            $table->boolean('treat_purch_consumed')->default(false);
            $table->boolean('treat_rejects_scrap')->default(false);
            $table->boolean('has_mfg_date')->default(false);
            $table->boolean('allow_expired_items')->default(false);
            $table->boolean('ignore_batches')->default(false);
            $table->boolean('ignore_godowns')->default(false);
            $table->integer('denominator')->default(1);
            $table->decimal('conversion', 10, 4)->nullable();

            // GST / HSN structured arrays
            $table->jsonb('gst_details')->nullable();
            $table->jsonb('hsn_details')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_stock_groups');
    }
};
