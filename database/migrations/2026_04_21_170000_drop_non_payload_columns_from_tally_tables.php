<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_ledger_groups', function (Blueprint $table) {
            $table->dropColumn(['is_revenue', 'affects_gross', 'is_addable']);
        });

        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->dropColumn(['is_cost_centre_applicable', 'tan_number', 'is_rcm_applicable', 'bank_details']);
        });

        Schema::table('tally_stock_groups', function (Blueprint $table) {
            $table->dropColumn(['parent_id', 'nature_of_group', 'should_add_quantities']);
        });

        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->dropColumn([
                'standard_cost', 'standard_price', 'costing_method',
                'is_batch_applicable', 'is_expiry_date_applicable',
                'reorder_level', 'reorder_quantity', 'maximum_quantity',
                'batch_allocations',
            ]);
        });

        Schema::table('tally_employees', function (Blueprint $table) {
            $table->dropColumn([
                'department', 'pan', 'aadhar', 'pf_number', 'uan_number', 'esi_number',
                'bank_name', 'bank_account_number', 'bank_ifsc',
                'addresses', 'salary_details',
            ]);
        });

        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->dropColumn(['pay_slip_name', 'ledger_name', 'rate']);
        });
    }

    public function down(): void
    {
        Schema::table('tally_ledger_groups', function (Blueprint $table) {
            $table->boolean('is_revenue')->nullable();
            $table->boolean('affects_gross')->nullable();
            $table->boolean('is_addable')->nullable();
        });

        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->boolean('is_cost_centre_applicable')->default(false);
            $table->string('tan_number')->nullable();
            $table->boolean('is_rcm_applicable')->default(false);
            $table->jsonb('bank_details')->nullable();
        });

        Schema::table('tally_stock_groups', function (Blueprint $table) {
            $table->integer('parent_id')->nullable();
            $table->string('nature_of_group')->nullable();
            $table->boolean('should_add_quantities')->default(false);
        });

        Schema::table('tally_stock_items', function (Blueprint $table) {
            $table->decimal('standard_cost', 12, 2)->nullable();
            $table->decimal('standard_price', 12, 2)->nullable();
            $table->string('costing_method')->nullable();
            $table->boolean('is_batch_applicable')->default(false);
            $table->boolean('is_expiry_date_applicable')->default(false);
            $table->decimal('reorder_level', 15, 4)->nullable();
            $table->decimal('reorder_quantity', 15, 4)->nullable();
            $table->decimal('maximum_quantity', 15, 4)->nullable();
            $table->jsonb('batch_allocations')->nullable();
        });

        Schema::table('tally_employees', function (Blueprint $table) {
            $table->string('department')->nullable();
            $table->string('pan')->nullable();
            $table->string('aadhar')->nullable();
            $table->string('pf_number')->nullable();
            $table->string('uan_number')->nullable();
            $table->string('esi_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->json('addresses')->nullable();
            $table->json('salary_details')->nullable();
        });

        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->string('pay_slip_name')->nullable();
            $table->string('ledger_name')->nullable();
            $table->decimal('rate', 15, 4)->nullable();
        });
    }
};
