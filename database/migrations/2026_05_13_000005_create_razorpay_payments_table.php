<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('razorpay_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('razorpay_payment_id')->unique();
            $table->string('razorpay_subscription_id')->index();
            $table->string('event_type');          // subscription.activated | subscription.charged
            $table->unsignedInteger('amount');     // in paise
            $table->string('currency', 8)->default('INR');
            $table->string('status', 32);          // captured | failed | etc.
            $table->string('method', 32)->nullable();
            $table->string('email')->nullable();
            $table->string('contact', 32)->nullable();
            $table->timestamp('razorpay_created_at')->nullable();
            $table->json('payload');               // full raw webhook payload
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('razorpay_payments');
    }
};
