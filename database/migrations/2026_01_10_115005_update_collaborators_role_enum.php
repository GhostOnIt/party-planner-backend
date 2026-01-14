<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old check constraint
        DB::statement("ALTER TABLE collaborators DROP CONSTRAINT collaborators_role_check");

        // Add new check constraint with all role values
        DB::statement("
            ALTER TABLE collaborators
            ADD CONSTRAINT collaborators_role_check
            CHECK (role IN (
                'owner',
                'editor',
                'viewer',
                'coordinator',
                'guest_manager',
                'planner',
                'accountant',
                'photographer',
                'supervisor',
                'reporter'
            ))
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new check constraint
        DB::statement("ALTER TABLE collaborators DROP CONSTRAINT collaborators_role_check");

        // Add back the old check constraint
        DB::statement("
            ALTER TABLE collaborators
            ADD CONSTRAINT collaborators_role_check
            CHECK (role IN ('owner', 'editor', 'viewer'))
        ");
    }
};
