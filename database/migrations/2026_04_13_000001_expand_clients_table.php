<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::table('clients', function (Blueprint $table) {
            $table->text('address')->nullable()->after('phone');
            $table->string('company')->nullable()->after('address');
            $table->string('tax_id')->nullable()->after('company');
            $table->text('notes')->nullable()->after('tax_id');
        });

        DB::statement('ALTER TABLE clients ADD COLUMN embedding vector(1536)');
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['address', 'company', 'tax_id', 'notes']);
        });

        DB::statement('ALTER TABLE clients DROP COLUMN IF EXISTS embedding');
    }
};
