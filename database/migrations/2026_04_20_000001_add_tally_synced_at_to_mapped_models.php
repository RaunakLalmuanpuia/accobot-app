<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['clients', 'vendors', 'products', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('tally_synced_at')->nullable()->after('updated_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['clients', 'vendors', 'products', 'invoices'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('tally_synced_at');
            });
        }
    }
};