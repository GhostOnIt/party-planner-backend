<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaborator_custom_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collaborator_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('custom_role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collaborator_id', 'custom_role_id']);
            $table->index('collaborator_id');
            $table->index('custom_role_id');
        });

        // Migrate legacy single custom_role_id -> pivot (so existing data keeps working).
        if (Schema::hasColumn('collaborators', 'custom_role_id')) {
            DB::table('collaborators')
                ->whereNotNull('custom_role_id')
                ->select(['id', 'custom_role_id'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) {
                    $payload = [];
                    $now = now();
                    foreach ($rows as $row) {
                        $payload[] = [
                            'collaborator_id' => $row->id,
                            'custom_role_id' => $row->custom_role_id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    if (!empty($payload)) {
                        DB::table('collaborator_custom_roles')->insertOrIgnore($payload);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborator_custom_roles');
    }
};















