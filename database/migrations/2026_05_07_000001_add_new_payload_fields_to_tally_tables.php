<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger Groups — new fields from real connector payload
        Schema::table('tally_ledger_groups', function (Blueprint $table) {
            $table->string('erp_id')->nullable()->after('tally_id');
            $table->boolean('is_sub_ledger')->nullable()->after('nature_of_group');
            $table->boolean('is_deemed_positive')->nullable()->after('is_sub_ledger');
            $table->boolean('used_for_calculation')->nullable()->after('is_deemed_positive');
            $table->string('method_to_allocate')->nullable()->after('used_for_calculation');
            $table->boolean('is_addable')->nullable()->after('method_to_allocate');
            $table->jsonb('tds_category_details')->nullable()->after('is_addable');
        });

        // Ledgers — interest and TDS fields from real connector payload
        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->string('type_of_interest_on')->nullable()->after('notes');
            $table->boolean('is_interest_on')->nullable()->after('type_of_interest_on');
            $table->boolean('is_interest_on_bill_wise')->nullable()->after('is_interest_on');
            $table->boolean('override_interest')->nullable()->after('is_interest_on_bill_wise');
            $table->boolean('interest_incl_day_of_addition')->nullable()->after('override_interest');
            $table->boolean('interest_incl_day_of_deduction')->nullable()->after('interest_incl_day_of_addition');
            $table->boolean('is_tds_applicable')->nullable()->after('interest_incl_day_of_deduction');
            $table->string('tds_deductee_type')->nullable()->after('is_tds_applicable');
        });

        // Employee Groups — salary defaults from real connector payload
        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->jsonb('salary_details')->nullable()->after('cost_centre_category');
        });

        // Employees — contact + address + salary details from real connector payload
        Schema::table('tally_employees', function (Blueprint $table) {
            $table->string('contact_number')->nullable()->after('spouse_name');
            $table->string('email_address')->nullable()->after('contact_number');
            $table->jsonb('address')->nullable()->after('email_address');
            $table->jsonb('salary_details')->nullable()->after('address');
        });

        // Attendance Types — aliases from real connector payload
        Schema::table('tally_attendance_types', function (Blueprint $table) {
            $table->jsonb('aliases')->nullable()->after('attendance_period');
        });
    }

    public function down(): void
    {
        Schema::table('tally_ledger_groups', function (Blueprint $table) {
            $table->dropColumn([
                'erp_id', 'is_sub_ledger', 'is_deemed_positive',
                'used_for_calculation', 'method_to_allocate', 'is_addable', 'tds_category_details',
            ]);
        });

        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->dropColumn([
                'type_of_interest_on', 'is_interest_on', 'is_interest_on_bill_wise',
                'override_interest', 'interest_incl_day_of_addition', 'interest_incl_day_of_deduction',
                'is_tds_applicable', 'tds_deductee_type',
            ]);
        });

        Schema::table('tally_employee_groups', function (Blueprint $table) {
            $table->dropColumn('salary_details');
        });

        Schema::table('tally_employees', function (Blueprint $table) {
            $table->dropColumn(['contact_number', 'email_address', 'address', 'salary_details']);
        });

        Schema::table('tally_attendance_types', function (Blueprint $table) {
            $table->dropColumn('aliases');
        });
    }
};
