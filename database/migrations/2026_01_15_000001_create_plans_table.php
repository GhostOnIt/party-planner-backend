<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->default(0); // Price in FCFA
            $table->unsignedInteger('duration_days')->default(30);
            $table->boolean('is_trial')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('limits')->nullable(); // Quotas: events.creations_per_billing_period, guests.max_per_event, etc.
            $table->json('features')->nullable(); // Features: budget.enabled, planning.enabled, etc.
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};

