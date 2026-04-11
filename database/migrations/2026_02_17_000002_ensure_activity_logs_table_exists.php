<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crée la table activity_logs si elle n'existe pas.
     * Doit s'exécuter juste après la transformation 2026_02_17_000001 et avant
     * 2026_02_18 (qui alter activity_logs). Logique alignée sur 2026_02_19_000001.
     */
    public function up(): void
    {
        if (Schema::hasTable('activity_logs')) {
            return;
        }

        if (Schema::hasTable('admin_activity_logs')) {
            $this->runTransform();

            return;
        }

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('actor_type', 20)->default('admin');
            $table->string('action');
            $table->string('model_type')->nullable();
            $table->string('model_id', 36)->nullable();
            $table->string('description');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('source', 30)->default('api');
            $table->string('page_url', 500)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->string('s3_key', 500)->nullable();
            $table->timestamp('s3_archived_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['model_type', 'model_id']);
            $table->index(['actor_type', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('session_id');
        });
    }

    private function runTransform(): void
    {
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });

        Schema::rename('admin_activity_logs', 'activity_logs');

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->renameColumn('admin_id', 'user_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('actor_type', 20)->default('admin')->after('user_id');
            $table->string('source', 30)->default('api')->after('user_agent');
            $table->string('page_url', 500)->nullable()->after('source');
            $table->string('session_id', 100)->nullable()->after('page_url');
            $table->json('metadata')->nullable()->after('session_id');
            $table->string('s3_key', 500)->nullable()->after('metadata');
            $table->timestamp('s3_archived_at')->nullable()->after('s3_key');
            $table->index(['actor_type', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('session_id');
        });

        \Illuminate\Support\Facades\DB::table('activity_logs')
            ->whereNull('actor_type')
            ->orWhere('actor_type', '')
            ->update(['actor_type' => 'admin', 'source' => 'api']);
    }

    public function down(): void
    {
        // Ne pas supprimer : risqué si données présentes
    }
};
