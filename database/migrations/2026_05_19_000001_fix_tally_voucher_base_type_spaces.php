<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE tally_vouchers SET voucher_base_type = 'CreditNote' WHERE voucher_base_type = 'Credit Note'");
        DB::statement("UPDATE tally_vouchers SET voucher_base_type = 'DebitNote'  WHERE voucher_base_type = 'Debit Note'");
    }

    public function down(): void
    {
        DB::statement("UPDATE tally_vouchers SET voucher_base_type = 'Credit Note' WHERE voucher_base_type = 'CreditNote'");
        DB::statement("UPDATE tally_vouchers SET voucher_base_type = 'Debit Note'  WHERE voucher_base_type = 'DebitNote'");
    }
};
