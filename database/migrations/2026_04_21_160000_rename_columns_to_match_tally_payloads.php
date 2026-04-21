<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_stock_groups', function (Blueprint $table) {
            $table->renameColumn('parent_name', 'parent');
        });

        Schema::table('tally_stock_categories', function (Blueprint $table) {
            $table->renameColumn('parent_name', 'parent');
        });

        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->renameColumn('parent_name', 'under');
        });

        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->renameColumn('pay_head_type', 'pay_type');
            $table->renameColumn('under_group', 'parent_group');
        });

        Schema::table('tally_employees', function (Blueprint $table) {
            $table->renameColumn('group_name', 'parent');
        });
    }

    public function down(): void
    {
        Schema::table('tally_stock_groups', function (Blueprint $table) {
            $table->renameColumn('parent', 'parent_name');
        });

        Schema::table('tally_stock_categories', function (Blueprint $table) {
            $table->renameColumn('parent', 'parent_name');
        });

        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->renameColumn('under', 'parent_name');
        });

        Schema::table('tally_pay_heads', function (Blueprint $table) {
            $table->renameColumn('pay_type', 'pay_head_type');
            $table->renameColumn('parent_group', 'under_group');
        });

        Schema::table('tally_employees', function (Blueprint $table) {
            $table->renameColumn('parent', 'group_name');
        });
    }
};
