<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = [
        'tally_ledger_groups',
        // tally_ledgers consolidated into 2026_04_19_000003_create_tally_ledgers_table
        'tally_stock_groups',
        'tally_stock_categories',
        'tally_stock_items',
        'tally_vouchers',
        'tally_godowns',
        'tally_statutory_masters',
        'tally_employee_groups',
        'tally_pay_heads',
        'tally_attendance_types',
        'tally_employees',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->integer('tally_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->integer('tally_id')->nullable(false)->change();
            });
        }
    }
};
