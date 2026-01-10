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
        // Migrate existing role data to the new table
        DB::statement("
            INSERT INTO collaborator_roles (collaborator_id, role, created_at, updated_at)
            SELECT id, role, NOW(), NOW()
            FROM collaborators
            WHERE role IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration is not reversible as it would lose data
        // The down method of the previous migration will handle table dropping
    }
};
