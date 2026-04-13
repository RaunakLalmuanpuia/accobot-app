<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->decimal('ai_confidence', 3, 2)->nullable()->after('review_status');
            $table->json('ai_suggestions')->nullable()->after('ai_confidence');
            $table->json('ai_metadata')->nullable()->after('ai_suggestions');
            $table->boolean('is_reconciled')->default(false)->after('ai_metadata');
            $table->foreignId('reconciled_invoice_id')->nullable()->constrained('invoices')->nullOnDelete()->after('is_reconciled');
            $table->date('reconciled_at')->nullable()->after('reconciled_invoice_id');
            $table->foreignId('applied_rule_id')->nullable()->after('reconciled_at');
            $table->string('party_reference')->nullable()->after('party_name');
            $table->boolean('is_duplicate')->default(false)->after('dedup_hash');

            $table->index('is_reconciled');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropIndex(['is_reconciled']);
            $table->dropConstrainedForeignId('reconciled_invoice_id');
            $table->dropColumn([
                'ai_confidence', 'ai_suggestions', 'ai_metadata',
                'is_reconciled', 'reconciled_at',
                'applied_rule_id', 'party_reference', 'is_duplicate',
            ]);
        });
    }
};
