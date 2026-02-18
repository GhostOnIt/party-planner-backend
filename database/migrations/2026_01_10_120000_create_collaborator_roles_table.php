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
        Schema::create('collaborator_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collaborator_id')->constrained()->cascadeOnDelete();
            $table->enum('role', [
                'owner', 'coordinator', 'guest_manager', 'planner',
                'accountant', 'photographer', 'supervisor', 'reporter',
                'editor', 'viewer'
            ]);
            $table->timestamps();

            $table->unique(['collaborator_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaborator_roles');
    }
};
