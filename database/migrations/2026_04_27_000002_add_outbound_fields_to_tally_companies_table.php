<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tally_companies', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->after('tally_connection_id')->index();
            $table->unsignedBigInteger('tally_id')->nullable()->after('company_guid');
            $table->string('action')->nullable()->after('tally_id');
        });
    }

    public function down(): void
    {
        Schema::table('tally_companies', function (Blueprint $table) {
            $table->dropColumn(['tenant_id', 'tally_id', 'action']);
        });
    }
};
