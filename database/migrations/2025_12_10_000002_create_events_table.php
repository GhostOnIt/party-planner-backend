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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('type', ['mariage', 'anniversaire', 'baby_shower', 'soiree', 'brunch', 'autre'])->default('autre');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('location')->nullable();
            $table->decimal('estimated_budget', 12, 2)->nullable();
            $table->decimal('actual_budget', 12, 2)->nullable();
            $table->string('theme')->nullable();
            $table->unsignedInteger('expected_guests_count')->nullable();
            $table->enum('status', ['draft', 'planning', 'confirmed', 'completed', 'cancelled'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
