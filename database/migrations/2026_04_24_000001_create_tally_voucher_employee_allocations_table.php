<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_voucher_employee_allocations', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('tally_voucher_id')->constrained('tally_vouchers')->cascadeOnDelete();
            $table->foreignId('tally_employee_id')->nullable()->constrained('tally_employees')->nullOnDelete();
            $table->string('employee_name');
            $table->string('employee_group')->nullable();
            // PayHeadEntries for Payroll vouchers; AttendanceEntries for Attendance vouchers
            $table->jsonb('entries')->default('[]');
            $table->decimal('net_payable', 15, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_voucher_employee_allocations');
    }
};
