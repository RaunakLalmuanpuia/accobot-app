<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tally_ledger_groups', 'is_revenue')) {
            Schema::table('tally_ledger_groups', function (Blueprint $table) {
                $table->dropColumn(['is_revenue', 'affects_gross', 'is_addable']);
            });
        }

        // tally_ledgers columns consolidated into 2026_04_19_000003_create_tally_ledgers_table

        if (Schema::hasColumn('tally_stock_groups', 'parent_id')) {
            Schema::table('tally_stock_groups', function (Blueprint $table) {
                $table->dropColumn(['parent_id', 'nature_of_group', 'should_add_quantities']);
            });
        }

        // tally_stock_items columns consolidated into 2026_04_19_000006_create_tally_stock_items_table

        if (Schema::hasColumn('tally_employees', 'department')) {
            Schema::table('tally_employees', function (Blueprint $table) {
                $table->dropColumn([
                    'department', 'pan', 'aadhar', 'pf_number', 'uan_number', 'esi_number',
                    'bank_name', 'bank_account_number', 'bank_ifsc',
                    'addresses', 'salary_details',
                ]);
            });
        }

        if (Schema::hasColumn('tally_pay_heads', 'pay_slip_name')) {
            Schema::table('tally_pay_heads', function (Blueprint $table) {
                $table->dropColumn(['pay_slip_name', 'ledger_name', 'rate']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tally_ledger_groups', 'is_revenue')) {
            Schema::table('tally_ledger_groups', function (Blueprint $table) {
                $table->boolean('is_revenue')->nullable();
                $table->boolean('affects_gross')->nullable();
                $table->boolean('is_addable')->nullable();
            });
        }

        // tally_ledgers columns consolidated into 2026_04_19_000003_create_tally_ledgers_table

        if (! Schema::hasColumn('tally_stock_groups', 'parent_id')) {
            Schema::table('tally_stock_groups', function (Blueprint $table) {
                $table->integer('parent_id')->nullable();
                $table->string('nature_of_group')->nullable();
                $table->boolean('should_add_quantities')->default(false);
            });
        }

        // tally_stock_items columns consolidated into 2026_04_19_000006_create_tally_stock_items_table

        if (! Schema::hasColumn('tally_employees', 'department')) {
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
        }

        if (! Schema::hasColumn('tally_pay_heads', 'pay_slip_name')) {
            Schema::table('tally_pay_heads', function (Blueprint $table) {
                $table->string('pay_slip_name')->nullable();
                $table->string('ledger_name')->nullable();
                $table->decimal('rate', 15, 4)->nullable();
            });
        }
    }
};
