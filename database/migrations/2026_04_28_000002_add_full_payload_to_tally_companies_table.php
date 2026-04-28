<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_companies', function (Blueprint $table) {
            // Identity / Contact
            $table->string('formal_name')->nullable()->after('company_name');
            $table->string('email')->nullable()->after('formal_name');
            $table->string('phone_number')->nullable()->after('email');
            $table->string('fax_number')->nullable()->after('phone_number');
            $table->string('website')->nullable()->after('fax_number');
            $table->string('pincode')->nullable()->after('country');
            $table->string('prior_state')->nullable()->after('pincode');
            $table->string('country_isd_code')->nullable()->after('prior_state');
            $table->string('branch_name')->nullable()->after('country_isd_code');
            $table->string('branch_name2')->nullable()->after('branch_name');
            $table->string('mobile_numbers')->nullable()->after('branch_name2');
            $table->string('logo_path')->nullable()->after('mobile_numbers');
            $table->string('price_level')->nullable()->after('logo_path');
            $table->string('connect_name')->nullable()->after('price_level');
            $table->string('db_name')->nullable()->after('connect_name');
            $table->string('name_alias')->nullable()->after('db_name');
            $table->string('company_number')->nullable()->after('name_alias');
            $table->string('statutory_version')->nullable()->after('company_number');
            $table->string('corporate_identity_no')->nullable()->after('statutory_version');

            // Tax registration
            $table->string('income_tax_number')->nullable()->after('corporate_identity_no');
            $table->string('sales_tax_number')->nullable()->after('income_tax_number');
            $table->string('interstate_st_number')->nullable()->after('sales_tax_number');
            $table->string('ta_number')->nullable()->after('interstate_st_number');

            // GST
            $table->string('gst_registration_number')->nullable()->after('ta_number');
            $table->string('gst_registration_type')->nullable()->after('gst_registration_number');
            $table->string('gst_applicable_date')->nullable()->after('gst_registration_type');
            $table->string('gst_applicability')->nullable()->after('gst_applicable_date');
            $table->string('cmp_type_of_supply')->nullable()->after('gst_applicability');
            $table->string('hsn_applicability')->nullable()->after('cmp_type_of_supply');
            $table->string('eway_bill_applicable_type')->nullable()->after('hsn_applicability');
            $table->string('eway_bill_interstate_threshold')->nullable()->after('eway_bill_applicable_type');

            // Financial periods
            $table->string('starting_from')->nullable()->after('eway_bill_interstate_threshold');
            $table->string('books_from')->nullable()->after('starting_from');
            $table->string('audited_upto')->nullable()->after('books_from');
            $table->string('fifo_applicable_from')->nullable()->after('audited_upto');
            $table->string('returns_start_from')->nullable()->after('fifo_applicable_from');
            $table->string('this_year_beg')->nullable()->after('returns_start_from');
            $table->string('this_year_end')->nullable()->after('this_year_beg');
            $table->string('prev_year_beg')->nullable()->after('this_year_end');
            $table->string('prev_year_end')->nullable()->after('prev_year_beg');
            $table->string('this_quarter_beg')->nullable()->after('prev_year_end');
            $table->string('this_quarter_end')->nullable()->after('this_quarter_beg');
            $table->string('prev_quarter_beg')->nullable()->after('this_quarter_end');
            $table->string('prev_quarter_end')->nullable()->after('prev_quarter_beg');

            // Address lines
            $table->string('address1')->nullable()->after('prev_quarter_end');
            $table->string('address2')->nullable()->after('address1');
            $table->string('address3')->nullable()->after('address2');
            $table->string('address4')->nullable()->after('address3');
            $table->string('address5')->nullable()->after('address4');

            // JSON blobs
            $table->jsonb('feature_flags')->nullable()->after('address5');
            $table->jsonb('deductor_details')->nullable()->after('feature_flags');
            $table->jsonb('legacy_tax_details')->nullable()->after('deductor_details');
        });
    }

    public function down(): void
    {
        Schema::table('tally_companies', function (Blueprint $table) {
            $table->dropColumn([
                'formal_name', 'email', 'phone_number', 'fax_number', 'website',
                'pincode', 'prior_state', 'country_isd_code', 'branch_name', 'branch_name2',
                'mobile_numbers', 'logo_path', 'price_level', 'connect_name', 'db_name',
                'name_alias', 'company_number', 'statutory_version', 'corporate_identity_no',
                'income_tax_number', 'sales_tax_number', 'interstate_st_number', 'ta_number',
                'gst_registration_number', 'gst_registration_type', 'gst_applicable_date',
                'gst_applicability', 'cmp_type_of_supply', 'hsn_applicability',
                'eway_bill_applicable_type', 'eway_bill_interstate_threshold',
                'starting_from', 'books_from', 'audited_upto', 'fifo_applicable_from',
                'returns_start_from', 'this_year_beg', 'this_year_end', 'prev_year_beg',
                'prev_year_end', 'this_quarter_beg', 'this_quarter_end', 'prev_quarter_beg',
                'prev_quarter_end', 'address1', 'address2', 'address3', 'address4', 'address5',
                'feature_flags', 'deductor_details', 'legacy_tax_details',
            ]);
        });
    }
};
