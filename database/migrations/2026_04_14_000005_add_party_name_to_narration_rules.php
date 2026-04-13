<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('narration_rules', function (Blueprint $table) {
            $table->string('party_name')->nullable()->after('note_template');
        });
    }

    public function down(): void
    {
        Schema::table('narration_rules', function (Blueprint $table) {
            $table->dropColumn('party_name');
        });
    }
};
