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
        Schema::create('communication_spots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('type', ['banner', 'poll'])->default('banner');
            
            // Content
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('badge')->nullable();
            $table->enum('badge_type', ['live', 'new', 'promo'])->default('new');
            
            // Banner specific - stored as JSON
            $table->json('primary_button')->nullable();
            $table->json('secondary_button')->nullable();
            
            // Poll specific
            $table->string('poll_question')->nullable();
            $table->json('poll_options')->nullable();
            
            // Administration
            $table->boolean('is_active')->default(false);
            $table->json('display_locations')->default('["dashboard"]');
            $table->integer('priority')->default(0);
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            
            // Targeting
            $table->json('target_roles')->nullable();
            
            // Stats
            $table->integer('views')->default(0);
            $table->integer('clicks')->default(0);
            $table->json('votes')->nullable(); // For polls: { "option_id": count }
            
            $table->timestamps();
        });
        
        // Table for tracking user votes on polls
        Schema::create('communication_spot_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('spot_id')->constrained('communication_spots')->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->string('option_id');
            $table->timestamps();
            
            $table->unique(['spot_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_spot_votes');
        Schema::dropIfExists('communication_spots');
    }
};
