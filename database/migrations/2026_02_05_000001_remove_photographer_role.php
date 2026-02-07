<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the photographer role: migrate existing data then update constraints.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // Migrate any collaborator with role 'photographer' to 'supervisor'
        DB::table('collaborators')->where('role', 'photographer')->update(['role' => 'supervisor']);

        if (Schema::hasTable('collaborator_roles')) {
            DB::table('collaborator_roles')->where('role', 'photographer')->update(['role' => 'supervisor']);
        }

        if ($driver !== 'sqlite') {
            // PostgreSQL: drop and re-add check constraint without 'photographer'
            DB::statement('ALTER TABLE collaborators DROP CONSTRAINT IF EXISTS collaborators_role_check');
            DB::statement("
                ALTER TABLE collaborators
                ADD CONSTRAINT collaborators_role_check
                CHECK (role IN (
                    'owner', 'editor', 'viewer', 'coordinator', 'guest_manager',
                    'planner', 'accountant', 'supervisor', 'reporter'
                ))
            ");
        }
    }

    /**
     * Reverse: re-add photographer to constraint (data is already changed).
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'sqlite') {
            DB::statement('ALTER TABLE collaborators DROP CONSTRAINT IF EXISTS collaborators_role_check');
            DB::statement("
                ALTER TABLE collaborators
                ADD CONSTRAINT collaborators_role_check
                CHECK (role IN (
                    'owner', 'editor', 'viewer', 'coordinator', 'guest_manager',
                    'planner', 'accountant', 'photographer', 'supervisor', 'reporter'
                ))
            ");
        }
    }
};
