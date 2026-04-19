<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');

            // Classification
            $table->string('ledger_name');
            $table->string('group_name')->nullable();
            $table->string('parent_group')->nullable();
            $table->string('ledger_category')->nullable();

            // Flags
            $table->boolean('is_bill_wise_on')->default(false);
            $table->boolean('inventory_affected')->default(false);
            $table->boolean('is_cost_centre_applicable')->default(false);

            // GST / Tax
            $table->string('gstin_number')->nullable();
            $table->string('pan_number')->nullable();
            $table->string('tan_number')->nullable();
            $table->string('gst_type')->nullable();
            $table->boolean('is_rcm_applicable')->default(false);

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
            $table->jsonb('addresses')->nullable();
            $table->string('state_name')->nullable();
            $table->string('country_name')->nullable();
            $table->string('pin_code')->nullable();

            // Credit Terms
            $table->integer('credit_period')->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();

            // Opening Balance
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->string('opening_balance_type')->nullable();

            // Bank Details
            $table->jsonb('bank_details')->nullable();

            // Aliases
            $table->jsonb('aliases')->nullable();

            // Other
            $table->text('description')->nullable();
            $table->text('notes')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();

            // Accobot mapping
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
