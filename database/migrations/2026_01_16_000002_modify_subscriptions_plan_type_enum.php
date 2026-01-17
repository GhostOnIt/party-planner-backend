<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to drop the enum constraint and recreate it
        // or change the column to string type
        if (DB::getDriverName() === 'pgsql') {
            // Drop the check constraint
            DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_plan_type_check");
            
            // Change to string type (more flexible for dynamic plans)
            DB::statement("ALTER TABLE subscriptions ALTER COLUMN plan_type TYPE VARCHAR(255)");
        } else {
            // For MySQL, change enum to string
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->string('plan_type')->default('starter')->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Revert to enum (only starter and pro for backward compatibility)
            DB::statement("ALTER TABLE subscriptions ALTER COLUMN plan_type TYPE VARCHAR(255)");
            DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_plan_type_check CHECK (plan_type IN ('starter', 'pro'))");
        } else {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->enum('plan_type', ['starter', 'pro'])->default('starter')->change();
            });
        }
    }
};

