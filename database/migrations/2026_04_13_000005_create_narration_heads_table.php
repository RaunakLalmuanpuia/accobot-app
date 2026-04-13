<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('narration_heads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['credit', 'debit', 'both'])->default('both');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('narration_sub_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('narration_head_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('ledger_code')->nullable();
            $table->string('ledger_name')->nullable();
            $table->boolean('requires_party')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['narration_head_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('narration_sub_heads');
        Schema::dropIfExists('narration_heads');
    }
};
