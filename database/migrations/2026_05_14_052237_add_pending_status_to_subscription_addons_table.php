<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE subscription_addons DROP CONSTRAINT IF EXISTS subscription_addons_status_check');
        DB::statement("ALTER TABLE subscription_addons ADD CONSTRAINT subscription_addons_status_check CHECK (status IN ('active', 'halted', 'cancelled', 'pending'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subscription_addons DROP CONSTRAINT IF EXISTS subscription_addons_status_check');
        DB::statement("ALTER TABLE subscription_addons ADD CONSTRAINT subscription_addons_status_check CHECK (status IN ('active', 'halted', 'cancelled'))");
    }
};
