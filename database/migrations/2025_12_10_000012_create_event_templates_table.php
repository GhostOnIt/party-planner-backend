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
        Schema::create('event_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('event_type', ['mariage', 'anniversaire', 'baby_shower', 'soiree', 'brunch', 'autre']);
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('default_tasks')->nullable();
            $table->json('default_budget_categories')->nullable();
            $table->json('suggested_themes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_templates');
    }
};
