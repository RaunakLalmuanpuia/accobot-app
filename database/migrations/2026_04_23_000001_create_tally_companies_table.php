<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tally_companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tally_connection_id')->constrained('tally_connections')->cascadeOnDelete();
            $table->string('company_guid');
            $table->string('company_name')->nullable();
            $table->string('licence_type')->nullable();
            $table->string('licence_number')->nullable();
            $table->timestamps();

            $table->unique(['tally_connection_id', 'company_guid']);
            $table->index('tally_connection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tally_companies');
    }
};
