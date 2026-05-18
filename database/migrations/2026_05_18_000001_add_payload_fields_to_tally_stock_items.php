<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: canonical create migration already includes these columns on fresh installs
        if (Schema::hasColumn('tally_stock_items', 'guid')) {
            return;
        }

        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->string('guid')->nullable()->after('action');

            $table->string('reporting_uom')->nullable()->after('denominator');
            $table->string('reporting_uom_date')->nullable()->after('reporting_uom');

            $table->string('hsn_desc')->nullable()->after('hsn_code');
            $table->string('type_of_supply')->nullable()->after('hsn_desc');

            $table->string('tcs_applicable')->nullable()->after('type_of_supply');
            $table->string('tcs_category')->nullable()->after('tcs_applicable');

            $table->boolean('inclusive_tax')->default(false)->after('mrp_rate');
            $table->boolean('modify_mrp_rate')->default(false)->after('inclusive_tax');
            $table->boolean('calc_on_mrp')->default(false)->after('modify_mrp_rate');
            $table->boolean('mrp_incl_of_tax')->default(false)->after('calc_on_mrp');
            $table->decimal('basic_rate_of_excise', 10, 4)->nullable()->after('mrp_incl_of_tax');

            $table->string('costing_method')->nullable()->after('basic_rate_of_excise');
            $table->string('valuation_method')->nullable()->after('costing_method');

            $table->string('sales_ledger')->nullable()->after('valuation_method');
            $table->decimal('sales_ledger_rate', 5, 2)->nullable()->after('sales_ledger');
            $table->string('purchase_ledger')->nullable()->after('sales_ledger_rate');
            $table->decimal('purchase_ledger_rate', 5, 2)->nullable()->after('purchase_ledger');

            $table->boolean('is_batch_wise')->default(false)->after('closing_value');
            $table->boolean('is_perishable')->default(false)->after('is_batch_wise');
            $table->boolean('has_mfg_date')->default(false)->after('is_perishable');
            $table->boolean('allow_expired_items')->default(false)->after('has_mfg_date');
            $table->boolean('ignore_batches')->default(false)->after('allow_expired_items');
            $table->boolean('ignore_godowns')->default(false)->after('ignore_batches');
            $table->boolean('ignore_phys_diff')->default(false)->after('ignore_godowns');
            $table->boolean('ignore_neg_stock')->default(false)->after('ignore_phys_diff');
            $table->boolean('treat_sales_as_mfg')->default(false)->after('ignore_neg_stock');
            $table->boolean('treat_purch_consumed')->default(false)->after('treat_sales_as_mfg');
            $table->boolean('treat_rejects_scrap')->default(false)->after('treat_purch_consumed');
            $table->boolean('is_cost_centres_on')->default(false)->after('treat_rejects_scrap');
            $table->boolean('is_cost_tracking_on')->default(false)->after('is_cost_centres_on');

            $table->string('is_entry_tax_applicable')->nullable()->after('batch_allocations');
            $table->string('is_rate_inclusive_vat')->nullable()->after('is_entry_tax_applicable');
            $table->string('vat_base_unit')->nullable()->after('is_rate_inclusive_vat');

            $table->jsonb('gst_details_list')->nullable()->after('vat_base_unit');
            $table->jsonb('hsn_details_list')->nullable()->after('gst_details_list');
            $table->jsonb('vat_details')->nullable()->after('hsn_details_list');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('tally_stock_items', 'guid')) {
            return;
        }

        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->dropColumn([
                'guid',
                'reporting_uom', 'reporting_uom_date',
                'hsn_desc', 'type_of_supply',
                'tcs_applicable', 'tcs_category',
                'inclusive_tax', 'modify_mrp_rate', 'calc_on_mrp', 'mrp_incl_of_tax',
                'basic_rate_of_excise',
                'costing_method', 'valuation_method',
                'sales_ledger', 'sales_ledger_rate',
                'purchase_ledger', 'purchase_ledger_rate',
                'is_batch_wise', 'is_perishable', 'has_mfg_date', 'allow_expired_items',
                'ignore_batches', 'ignore_godowns', 'ignore_phys_diff', 'ignore_neg_stock',
                'treat_sales_as_mfg', 'treat_purch_consumed', 'treat_rejects_scrap',
                'is_cost_centres_on', 'is_cost_tracking_on',
                'is_entry_tax_applicable', 'is_rate_inclusive_vat', 'vat_base_unit',
                'gst_details_list', 'hsn_details_list', 'vat_details',
            ]);
        });
    }
};
