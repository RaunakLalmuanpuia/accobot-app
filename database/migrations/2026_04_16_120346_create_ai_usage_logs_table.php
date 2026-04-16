<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('agent');          // e.g. 'AccountingAgent', 'SmsParserAgent'
            $table->string('model')->nullable();    // e.g. 'gpt-4o', 'gpt-4o-mini'
            $table->string('provider')->nullable(); // e.g. 'openai'
            $table->string('call_type');      // 'chat' | 'structured' | 'embedding'
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('total_tokens')->default(0);
            $table->unsignedSmallInteger('tool_steps')->default(0);
            $table->json('context')->nullable(); // extra metadata (history turns, batch size, etc.)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
