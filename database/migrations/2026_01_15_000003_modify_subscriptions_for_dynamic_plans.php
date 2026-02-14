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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add plan_id for dynamic plans (nullable for migration)
            $table->foreignUuid('plan_id')->nullable()->after('event_id')->constrained()->nullOnDelete();
            
            // Add creations tracking
            $table->unsignedInteger('creations_used')->default(0)->after('payment_status');
            
            // Add status field for subscription lifecycle
            $table->string('status')->default('active')->after('creations_used');
            
            // Make event_id nullable for account-scope subscriptions
            $table->uuid('event_id')->nullable()->change();
            
            // Add billing period tracking
            $table->timestamp('starts_at')->nullable()->after('status');
            
            // Rename expires_at to ends_at for clarity (keep both for compatibility)
            // We'll use ends_at going forward
        });

        // Add index for common queries
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
            $table->index(['plan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['plan_id', 'status']);
            
            $table->dropForeign(['plan_id']);
            $table->dropColumn(['plan_id', 'creations_used', 'status', 'starts_at']);
        });
    }
};

