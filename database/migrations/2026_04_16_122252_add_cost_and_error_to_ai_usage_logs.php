<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->decimal('cost_usd', 10, 8)->default(0)->after('tool_steps');
            $table->boolean('is_error')->default(false)->after('cost_usd');
            $table->text('error_message')->nullable()->after('is_error');
        });
    }

    public function down(): void
    {
        Schema::table('ai_usage_logs', function (Blueprint $table) {
            $table->dropColumn(['cost_usd', 'is_error', 'error_message']);
        });
    }
};
