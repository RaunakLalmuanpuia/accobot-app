<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_pay_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->integer('tally_id');
            $table->integer('alter_id');
            $table->string('action')->default('Create');
            $table->string('name');
            $table->string('pay_head_type')->nullable();       // Earning, Deduction, Employer's Statutory Contributions, etc.
            $table->string('pay_slip_name')->nullable();
            $table->string('under_group')->nullable();
            $table->string('ledger_name')->nullable();
            $table->string('calculation_type')->nullable();    // On Attendance, As Computed Value, Fixed, etc.
            $table->decimal('rate', 15, 4)->nullable();
            $table->string('rate_period')->nullable();         // Monthly, Daily, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'tally_id']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_pay_heads');
    }
};
