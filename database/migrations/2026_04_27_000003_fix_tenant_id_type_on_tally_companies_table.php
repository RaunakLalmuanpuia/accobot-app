<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tally_companies DROP COLUMN IF EXISTS tenant_id');
        DB::statement('ALTER TABLE tally_companies ADD COLUMN tenant_id uuid NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS tally_companies_tenant_id_index ON tally_companies (tenant_id)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tally_companies DROP COLUMN IF EXISTS tenant_id');
    }
};
