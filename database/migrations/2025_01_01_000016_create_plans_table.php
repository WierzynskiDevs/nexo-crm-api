<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique(); // starter|growth|scale|enterprise
            $table->string('name');
            $table->unsignedInteger('price_cents');
            $table->unsignedInteger('seats_limit')->nullable(); // null = ilimitado
            $table->unsignedInteger('storage_limit_mb')->nullable();
            $table->jsonb('features')->default('[]');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
