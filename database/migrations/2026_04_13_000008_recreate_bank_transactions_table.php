<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('bank_transactions');

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();

            $table->string('bank_account_name')->nullable();
            $table->date('transaction_date');
            $table->string('bank_reference')->nullable();
            $table->text('raw_narration');
            $table->enum('type', ['credit', 'debit']);
            $table->decimal('amount', 15, 2);
            $table->decimal('balance_after', 15, 2)->nullable();

            $table->foreignId('narration_head_id')->nullable()->constrained('narration_heads')->nullOnDelete();
            $table->foreignId('narration_sub_head_id')->nullable()->constrained('narration_sub_heads')->nullOnDelete();
            $table->string('narration_note')->nullable();
            $table->string('party_name')->nullable();

            $table->enum('narration_source', ['manual', 'ai_suggested', 'rule_based'])->default('manual');
            $table->enum('review_status', ['pending', 'reviewed', 'flagged'])->default('pending');

            $table->string('import_source')->nullable();
            $table->string('import_batch_id')->nullable();
            $table->string('dedup_hash')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'transaction_date']);
            $table->index('review_status');
            $table->index('type');
            $table->unique(['tenant_id', 'dedup_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
