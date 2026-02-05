<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Remove the category check constraint so user-defined budget category slugs are allowed.
     * In PostgreSQL Laravel creates varchar(255) with a CHECK; we only drop the constraint.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE budget_items DROP CONSTRAINT IF EXISTS budget_items_category_check');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Set any custom category slugs to 'other' before re-adding the constraint
        $allowed = ['location', 'catering', 'decoration', 'entertainment', 'photography', 'transportation', 'other'];
        DB::table('budget_items')
            ->whereNotIn('category', $allowed)
            ->update(['category' => 'other']);

        DB::statement("
            ALTER TABLE budget_items
            ADD CONSTRAINT budget_items_category_check
            CHECK (category IN ('location', 'catering', 'decoration', 'entertainment', 'photography', 'transportation', 'other'))
        ");
    }
};
