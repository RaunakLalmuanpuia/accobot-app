<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Make role_id nullable so ca_client invitations don't require a role
            $table->dropForeign(['role_id']);
            $table->unsignedBigInteger('role_id')->nullable()->change();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();

            $table->string('invitation_type')->default('member')->after('status'); // member | ca_client
            $table->json('meta')->nullable()->after('invitation_type');
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            $table->dropColumn(['invitation_type', 'meta']);
            $table->dropForeign(['role_id']);
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
            $table->foreign('role_id')->references('id')->on('roles')->cascadeOnDelete();
        });
    }
};
