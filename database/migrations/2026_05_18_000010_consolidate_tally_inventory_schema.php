<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Consolidation migration for tally inventory tables.
// Applies all changes that were previously in piecemeal patch migrations
// (deleted during schema consolidation on 2026-05-18). Guards on hasColumn
// so this is a no-op on fresh installs where canonical creates already include
// all columns.
return new class extends Migration
{
    public function up(): void
    {
        // ── tally_stock_groups ────────────────────────────────────────────────

        // Older installs used 'parent'; canonical create uses 'parent_name'.
        if (Schema::hasColumn('tally_stock_groups', 'parent') &&
            ! Schema::hasColumn('tally_stock_groups', 'parent_name')) {
            Schema::table('tally_stock_groups', function (Blueprint $table) {
                $table->renameColumn('parent', 'parent_name');
            });
        }

        if (! Schema::hasColumn('tally_stock_groups', 'costing_method')) {
            Schema::table('tally_stock_groups', function (Blueprint $table) {
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
                $table->jsonb('gst_details')->nullable();
                $table->jsonb('hsn_details')->nullable();
            });
        }

        if (! Schema::hasColumn('tally_stock_groups', 'aliases')) {
            Schema::table('tally_stock_groups', function (Blueprint $table) {
                $table->jsonb('aliases')->nullable();
            });
        }

        // ── tally_stock_categories ────────────────────────────────────────────

        if (Schema::hasColumn('tally_stock_categories', 'parent') &&
            ! Schema::hasColumn('tally_stock_categories', 'parent_name')) {
            Schema::table('tally_stock_categories', function (Blueprint $table) {
                $table->renameColumn('parent', 'parent_name');
            });
        }

        if (! Schema::hasColumn('tally_stock_categories', 'aliases')) {
            Schema::table('tally_stock_categories', function (Blueprint $table) {
                $table->jsonb('aliases')->nullable();
            });
        }

        // ── tally_stock_items ─────────────────────────────────────────────────

        if (! Schema::hasColumn('tally_stock_items', 'guid')) {
            Schema::table('tally_stock_items', function (Blueprint $table) {
                $table->string('guid')->nullable()->after('action');
                $table->string('reporting_uom')->nullable();
                $table->string('reporting_uom_date')->nullable();
                $table->string('hsn_desc')->nullable();
                $table->string('type_of_supply')->nullable();
                $table->string('tcs_applicable')->nullable();
                $table->string('tcs_category')->nullable();
                $table->boolean('inclusive_tax')->default(false);
                $table->boolean('modify_mrp_rate')->default(false);
                $table->boolean('calc_on_mrp')->default(false);
                $table->boolean('mrp_incl_of_tax')->default(false);
                $table->decimal('basic_rate_of_excise', 10, 4)->nullable();
                $table->string('costing_method')->nullable();
                $table->string('valuation_method')->nullable();
                $table->string('sales_ledger')->nullable();
                $table->decimal('sales_ledger_rate', 5, 2)->nullable();
                $table->string('purchase_ledger')->nullable();
                $table->decimal('purchase_ledger_rate', 5, 2)->nullable();
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
                $table->string('is_entry_tax_applicable')->nullable();
                $table->string('is_rate_inclusive_vat')->nullable();
                $table->string('vat_base_unit')->nullable();
                $table->jsonb('gst_details_list')->nullable();
                $table->jsonb('hsn_details_list')->nullable();
                $table->jsonb('vat_details')->nullable();
            });
        }

        // ── tally_units ───────────────────────────────────────────────────────

        if (! Schema::hasColumn('tally_units', 'guid')) {
            Schema::table('tally_units', function (Blueprint $table) {
                $table->string('guid')->nullable();
                $table->string('original_name')->nullable();
                $table->boolean('is_simple_unit')->default(true);
                $table->string('is_gst_excluded')->nullable();
                $table->decimal('conversion', 10, 4)->nullable();
                $table->jsonb('reporting_uqc_details')->nullable();
            });
        }

        // Add unique(tenant_id, name) if not already present (fresh installs
        // get it from the canonical create; existing installs need it here).
        $indexExists = DB::selectOne("
            SELECT 1 FROM pg_indexes
            WHERE tablename = 'tally_units'
              AND indexname  = 'tally_units_tenant_name_unique'
        ");
        if (! $indexExists) {
            DB::statement('
                DELETE FROM tally_units
                WHERE id NOT IN (
                    SELECT MAX(id)
                    FROM tally_units
                    GROUP BY tenant_id, name
                )
            ');
            Schema::table('tally_units', function (Blueprint $table) {
                $table->unique(['tenant_id', 'name'], 'tally_units_tenant_name_unique');
            });
        }

        // ── tally_godowns ─────────────────────────────────────────────────────

        if (! Schema::hasColumn('tally_godowns', 'has_no_space')) {
            Schema::table('tally_godowns', function (Blueprint $table) {
                $table->boolean('has_no_space')->default(false);
                $table->boolean('has_no_stock')->default(false);
                $table->boolean('is_external')->default(false);
                $table->boolean('is_internal')->default(false);
                $table->jsonb('address')->nullable();
                $table->jsonb('aliases')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Renames and column additions are intentional consolidations — not reversible.
    }
};
