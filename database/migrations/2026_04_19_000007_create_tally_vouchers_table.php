<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');
            $table->string('voucher_type');
            $table->string('voucher_number')->nullable();
            $table->date('voucher_date')->nullable();
            $table->string('reference')->nullable();
            $table->string('reference_date')->nullable();

            // Party
            $table->string('party_name')->nullable();
            $table->foreignId('party_tally_ledger_id')->nullable()->constrained('tally_ledgers')->nullOnDelete();
            $table->decimal('voucher_total', 15, 2)->nullable();

            // Flags
            $table->boolean('is_invoice')->default(false);
            $table->boolean('is_deleted')->default(false);

            // Dispatch / Shipping
            $table->string('place_of_supply')->nullable();
            $table->string('delivery_note_no')->nullable();
            $table->string('delivery_note_date')->nullable();
            $table->string('dispatch_doc_no')->nullable();
            $table->string('dispatch_through')->nullable();
            $table->string('destination')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('lr_no')->nullable();
            $table->string('lr_date')->nullable();
            $table->string('motor_vehicle_no')->nullable();

            // Order
            $table->string('order_no')->nullable();
            $table->string('order_date')->nullable();
            $table->string('terms_of_payment')->nullable();
            $table->string('terms_of_delivery')->nullable();
            $table->string('other_references')->nullable();

            // Buyer (Sales)
            $table->string('buyer_name')->nullable();
            $table->string('buyer_alias')->nullable();
            $table->string('buyer_gstin')->nullable();
            $table->string('buyer_pin_code')->nullable();
            $table->string('buyer_state')->nullable();
            $table->string('buyer_country')->nullable();
            $table->string('buyer_gst_registration_type')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_mobile')->nullable();
            $table->jsonb('buyer_address')->nullable();

            // Consignee (Sales)
            $table->string('consignee_name')->nullable();
            $table->string('consignee_gstin')->nullable();
            $table->string('consignee_tally_group')->nullable();
            $table->string('consignee_pin_code')->nullable();
            $table->string('consignee_state')->nullable();
            $table->string('consignee_country')->nullable();
            $table->string('consignee_gst_registration_type')->nullable();

            // e-Invoice
            $table->string('irn')->nullable();
            $table->string('acknowledgement_no')->nullable();
            $table->string('acknowledgement_date')->nullable();
            $table->text('qr_code')->nullable();

            // Other
            $table->text('narration')->nullable();
            $table->string('cost_centre')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();

            // Accobot mapping
            $table->foreignId('mapped_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index(['tenant_id', 'voucher_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_vouchers');
    }
};
