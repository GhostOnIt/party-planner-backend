<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Custom roles become user-scoped: visible and manageable only by their creator.
     * System roles remain global (from enum).
     */
    public function up(): void
    {
        if (!Schema::hasColumn('custom_roles', 'user_id')) {
            Schema::table('custom_roles', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
            DB::table('custom_roles')->update([
                'user_id' => DB::raw('created_by'),
            ]);
        }

        // Remove old per-event system roles (system roles are now global from enum; no DB rows)
        DB::table('custom_roles')->where('is_system', true)->delete();

        // Deduplicate: keep one role per (user_id, name), delete the rest (e.g. legacy duplicates)
        $dupes = DB::table('custom_roles')
            ->select('user_id', 'name')
            ->groupBy('user_id', 'name')
            ->havingRaw('count(*) > 1')
            ->get();
        foreach ($dupes as $row) {
            $ids = DB::table('custom_roles')
                ->where('user_id', $row->user_id)
                ->where('name', $row->name)
                ->orderBy('id')
                ->pluck('id');
            DB::table('custom_roles')->whereIn('id', $ids->slice(1)->values()->all())->delete();
        }

        if (Schema::hasColumn('custom_roles', 'event_id')) {
            Schema::table('custom_roles', function (Blueprint $table) {
                $table->dropUnique(['event_id', 'name']);
                $table->dropForeign(['event_id']);
                $table->dropColumn('event_id');
            });
        }

        // Add unique/index if not already present (e.g. after re-run)
        if (!Schema::hasColumn('custom_roles', 'event_id')) {
            Schema::table('custom_roles', function (Blueprint $table) {
                if (!$this->indexExists('custom_roles', 'custom_roles_user_id_name_unique')) {
                    $table->unique(['user_id', 'name']);
                }
                if (!$this->indexExists('custom_roles', 'custom_roles_user_id_index')) {
                    $table->index('user_id');
                }
            });
        }

        $driver = Schema::getConnection()->getDriverName();
        if (Schema::hasColumn('custom_roles', 'user_id')) {
            try {
                if ($driver === 'pgsql') {
                    DB::statement('ALTER TABLE custom_roles ALTER COLUMN user_id SET NOT NULL');
                } else {
                    DB::statement('ALTER TABLE custom_roles MODIFY user_id BIGINT UNSIGNED NOT NULL');
                }
            } catch (\Throwable $e) {
                // Column might already be NOT NULL
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $conn = Schema::getConnection();
        $driver = $conn->getDriverName();
        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
                [$table, $index]
            );
            return $result !== null;
        }
        return false;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('custom_roles', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('custom_roles', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // We cannot reliably backfill event_id from user_id (user may have many events).
        // Leave event_id null for rolled-back rows; app must handle or re-migrate.
        Schema::table('custom_roles', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->unique(['event_id', 'name']);
        });
    }
};
