<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('failed_jobs') || Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('UPDATE failed_jobs SET uuid = id WHERE uuid IS NULL');
            DB::statement('ALTER TABLE failed_jobs ALTER COLUMN uuid SET NOT NULL');
            return;
        }

        DB::table('failed_jobs')->whereNull('uuid')->update(['uuid' => DB::raw('id')]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('failed_jobs') || !Schema::hasColumn('failed_jobs', 'uuid')) {
            return;
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
