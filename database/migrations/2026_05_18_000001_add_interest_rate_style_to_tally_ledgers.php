<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->decimal('interest_rate', 10, 4)->nullable()->after('is_interest_incl_last_day');
            $table->string('interest_style', 50)->nullable()->after('interest_rate'); // "365-Day Year" / "Calendar Month"
        });
    }

    public function down(): void
    {
        Schema::table('tally_ledgers', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'interest_style']);
        });
    }
};
