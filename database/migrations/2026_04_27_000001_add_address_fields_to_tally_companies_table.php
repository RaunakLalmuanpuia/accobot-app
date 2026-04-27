<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_companies', function (Blueprint $table) {
            $table->text('address')->nullable()->after('company_name');
            $table->string('state')->nullable()->after('address');
            $table->string('country')->nullable()->after('state');
            $table->string('tally_serial_no')->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('tally_companies', function (Blueprint $table) {
            $table->dropColumn(['address', 'state', 'country', 'tally_serial_no']);
        });
    }
};
