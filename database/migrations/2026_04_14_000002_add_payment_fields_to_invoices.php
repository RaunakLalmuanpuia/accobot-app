<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('total');
            $table->decimal('amount_due', 12, 2)->default(0)->after('amount_paid');
        });

        // Seed amount_due = total for all existing invoices
        DB::statement('UPDATE invoices SET amount_due = total WHERE amount_due = 0');

        // Expand the status enum to include 'partial'
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('draft','sent','paid','partial','overdue','cancelled'))");
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'amount_due']);
        });

        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS invoices_status_check");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check CHECK (status IN ('draft','sent','paid','overdue','cancelled'))");
    }
};
