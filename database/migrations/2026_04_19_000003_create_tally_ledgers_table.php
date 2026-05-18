<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Single source-of-truth migration for tally_ledgers.
// All columns derived from the Tally connector payload (Master Export 2026-05-16).
// Do NOT add piecemeal add-column migrations for this table — update this file instead.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            // Tally identity
            $table->integer('tally_id')->nullable();
            $table->integer('alter_id')->nullable();
            $table->string('action')->default('Create');
            $table->string('guid')->nullable();
            $table->string('currency_name')->nullable();
            $table->string('ledger_country_isd_code')->nullable();

            // Classification
            $table->string('ledger_name');
            $table->string('group_name')->nullable();
            $table->string('parent_group')->nullable();
            $table->string('ledger_category')->nullable();  // derived: customer/vendor/bank/tax/income/expense/asset/liability/other

            // Behaviour flags
            $table->boolean('is_bill_wise_on')->default(false);
            $table->boolean('inventory_affected')->default(false);
            $table->boolean('is_cost_centres_on')->nullable();
            $table->boolean('is_cost_tracking_on')->nullable();

            // GST
            $table->string('gstin_number')->nullable();
            $table->string('gst_type_ledger')->nullable();          // GSTTypeLedger — e.g. "Central Tax", "State Tax"
            $table->string('gst_registration_type')->nullable();    // Regular / Composition / Unregistered/Consumer
            $table->string('gst_applicable_from')->nullable();      // YYYYMMDD
            $table->boolean('is_gst_applicable')->nullable();
            $table->boolean('is_sez_party')->nullable();
            $table->boolean('is_transporter')->nullable();
            $table->boolean('is_common_party')->nullable();
            $table->boolean('is_other_territory_assessee')->nullable();
            $table->string('appropriate_for')->nullable();
            $table->boolean('is_rcm_applicable')->nullable();

            // PAN
            $table->string('pan_number')->nullable();
            $table->string('pan_applicable_from')->nullable();      // YYYYMMDD
            $table->string('name_on_pan')->nullable();

            // TDS / TCS
            $table->boolean('is_tds_applicable')->nullable();
            $table->string('tds_deductee_type')->nullable();
            $table->boolean('is_tds_projected')->nullable();
            $table->boolean('is_tcs_applicable')->nullable();

            // Legacy / statutory tax
            $table->string('vat_tin_number')->nullable();
            $table->string('tax_type')->nullable();                 // CST / Others
            $table->string('used_for_tax_type')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('service_category')->nullable();
            $table->string('importer_exporter_code')->nullable();

            // Contact
            $table->string('mailing_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_person_email')->nullable();
            $table->string('contact_person_email_cc')->nullable();
            $table->string('contact_person_fax')->nullable();
            $table->string('contact_person_website')->nullable();
            $table->string('contact_person_mobile')->nullable();

            // Address
            $table->jsonb('addresses')->nullable();                 // LedgerAddress array
            $table->string('state_name')->nullable();
            $table->string('country_name')->nullable();
            $table->string('pin_code')->nullable();

            // Credit
            $table->integer('credit_period')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->boolean('is_credit_days_check_on')->nullable();
            $table->boolean('override_credit_limit')->nullable();

            // Opening balance
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->string('opening_balance_type')->nullable();     // Dr / Cr

            // Interest
            $table->string('type_of_interest_on')->nullable();     // Voucher Date / Transaction Date
            $table->boolean('is_interest_on')->nullable();
            $table->boolean('is_interest_on_bill_wise')->nullable();
            $table->boolean('override_interest')->nullable();
            $table->boolean('override_adv_interest')->nullable();
            $table->boolean('is_interest_incl_last_day')->nullable();
            $table->boolean('interest_incl_day_of_addition')->nullable();
            $table->boolean('interest_incl_day_of_deduction')->nullable();

            // Bank — flat fields (top-level payload scalars for the ledger's own bank account)
            $table->string('bank_account_holder_name')->nullable();
            $table->string('swift_code')->nullable();
            $table->string('branch_name')->nullable();
            $table->string('bank_bsr_code')->nullable();
            $table->string('default_transfer_mode')->nullable();    // NEFT / RTGS / IMPS / UPI
            $table->boolean('is_cheque_printing_enabled')->nullable();

            // Party / payroll flags
            $table->boolean('is_related_party')->nullable();
            $table->boolean('for_payroll')->nullable();
            $table->boolean('use_for_esi_eligibility')->nullable();
            $table->string('is_e_cash_ledger')->nullable();
            $table->boolean('ignore_mismatch_with_warning')->nullable();
            $table->boolean('ignore_tds_exempt')->nullable();
            $table->string('it_exempt_applicable')->nullable();     // "Applicable" / ""

            // JSONB payload arrays
            $table->jsonb('aliases')->nullable();
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('bank_details')->nullable();              // account list: {BankName, IFSCode, AccountNumber, ...}
            $table->jsonb('bill_allocations')->nullable();          // {Date, BillName, Amount, AmountType}
            $table->jsonb('gst_registration_details')->nullable();  // {ApplicableFrom, GSTRegistrationType, GSTIN, PlaceOfSupply, ...}
            $table->jsonb('cheque_ranges')->nullable();             // {ChequebookName, ChequeFrom, ChequeTo, ChequeLeafAvailable}
            $table->jsonb('transfer_mode_limits')->nullable();      // {TransferMode, MaxTransferLimit, MinTransferLimit}
            $table->jsonb('interest_collection')->nullable();       // {InterestStyle, InterestRate}
            $table->jsonb('tds_category_details')->nullable();      // {NatureOfPayment, DeducteeType}
            $table->jsonb('tcs_category_details')->nullable();      // {NatureOfGoods}
            $table->jsonb('contact_details')->nullable();           // {ContactName, CountryISDCode, IsDefaultWhatsAppNum}
            $table->jsonb('deduct_in_same_vch_rules')->nullable();  // {NatureOfPayment}
            $table->jsonb('led_multi_address_list')->nullable();    // {AddressName, VATDealerType}

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();

            // Accobot auto-mapping
            $table->foreignId('mapped_client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('mapped_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_ledgers');
    }
};
