<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->string('gstin', 15)->nullable()->after('website');
            $table->string('pan', 10)->nullable()->after('gstin');
            $table->string('logo_url', 500)->nullable()->after('pan');
            $table->string('address_line1')->nullable()->after('logo_url');
            $table->string('address_line2')->nullable()->after('address_line1');
            $table->string('city', 100)->nullable()->after('address_line2');
            $table->string('state', 100)->nullable()->after('city');
            $table->string('pincode', 10)->nullable()->after('state');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'email', 'website', 'gstin', 'pan',
                'logo_url', 'address_line1', 'address_line2',
                'city', 'state', 'pincode',
            ]);
        });
    }
};
