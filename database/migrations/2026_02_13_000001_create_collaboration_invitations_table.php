<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Pending collaboration invitations for emails not yet registered.
     */
    public function up(): void
    {
        Schema::create('collaboration_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->json('roles')->nullable(); // system role values e.g. ['editor','viewer']
            $table->json('custom_role_ids')->nullable(); // custom role ids for event owner
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('invited_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaboration_invitations');
    }
};
