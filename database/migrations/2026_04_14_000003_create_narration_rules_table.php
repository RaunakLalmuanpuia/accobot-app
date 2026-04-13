<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narration_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('match_type');           // contains | starts_with | ends_with | exact | regex
            $table->string('match_value');
            $table->string('transaction_type');     // credit | debit | both
            $table->decimal('amount_min', 15, 2)->nullable();
            $table->decimal('amount_max', 15, 2)->nullable();
            $table->foreignId('narration_head_id')->nullable()->constrained('narration_heads')->nullOnDelete();
            $table->foreignId('narration_sub_head_id')->nullable()->constrained('narration_sub_heads')->nullOnDelete();
            $table->string('note_template')->nullable();
            $table->integer('priority')->default(10);
            $table->boolean('is_active')->default(true);
            $table->string('source')->default('manual'); // manual | learned
            $table->integer('match_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'match_value', 'match_type', 'transaction_type']);
            $table->index(['tenant_id', 'is_active', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narration_rules');
    }
};
