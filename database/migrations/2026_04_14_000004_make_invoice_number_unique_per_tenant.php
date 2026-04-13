<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * invoice_number was globally unique, but invoice numbers are only meaningful
 * within a single tenant's numbering sequence. Change to a per-tenant unique
 * constraint so different tenants can use the same invoice numbers independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['invoice_number']);
            $table->unique(['tenant_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'invoice_number']);
            $table->unique(['invoice_number']);
        });
    }
};
