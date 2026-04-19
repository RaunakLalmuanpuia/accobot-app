<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('report_type');
            $table->date('period_from')->nullable();
            $table->date('period_to');
            $table->jsonb('data');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('synced_at')->useCurrent();
            $table->timestamps();

            $table->index(['tenant_id', 'report_type', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_reports');
    }
};
