<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Transforme la table admin_activity_logs en activity_logs universelle.
     * - Renomme la table
     * - Renomme admin_id -> user_id
     * - Ajoute les colonnes : actor_type, source, page_url, session_id, metadata, s3_key, s3_archived_at
     * - Ajoute les index nécessaires
     */
    public function up(): void
    {
        // 1. Supprimer la FK AVANT de renommer la table (PostgreSQL garde le nom original)
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $table->dropForeign(['admin_id']);
        });

        // 2. Renommer la table
        Schema::rename('admin_activity_logs', 'activity_logs');

        // 3. Renommer la colonne admin_id -> user_id
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->renameColumn('admin_id', 'user_id');
        });

        // 4. Ajouter la génération automatique d'UUID pour la colonne id (si pas déjà présente)
        // Note: PostgreSQL nécessite une modification explicite de la colonne pour ajouter le default
        DB::statement('ALTER TABLE activity_logs ALTER COLUMN id SET DEFAULT gen_random_uuid()');

        // 5. Ajouter la nouvelle FK et les nouvelles colonnes
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Type d'acteur : admin, user, system, guest
            $table->string('actor_type', 20)->default('admin')->after('user_id');

            // Source du log : api, navigation, ui_interaction, system
            $table->string('source', 30)->default('api')->after('user_agent');

            // URL de la page (pour les logs frontend : navigation, ui_interaction)
            $table->string('page_url', 500)->nullable()->after('source');

            // ID de session pour regrouper les actions d'une même session
            $table->string('session_id', 100)->nullable()->after('page_url');

            // Métadonnées additionnelles (durée sur page, élément cliqué, etc.)
            $table->json('metadata')->nullable()->after('session_id');

            // Référence vers le fichier JSON sur S3
            $table->string('s3_key', 500)->nullable()->after('metadata');

            // Date d'archivage vers S3
            $table->timestamp('s3_archived_at')->nullable()->after('s3_key');

            // Nouveaux index
            $table->index(['actor_type', 'created_at'], 'activity_logs_actor_type_created_at_index');
            $table->index(['source', 'created_at'], 'activity_logs_source_created_at_index');
            $table->index('session_id', 'activity_logs_session_id_index');
            $table->index('s3_archived_at', 'activity_logs_s3_archived_at_index');
        });

        // 6. Mettre à jour les données existantes : tous les anciens logs sont de type admin/api
        DB::table('activity_logs')
            ->whereNull('actor_type')
            ->orWhere('actor_type', '')
            ->update([
                'actor_type' => 'admin',
                'source' => 'api',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Supprimer les nouveaux index
            $table->dropIndex('activity_logs_actor_type_created_at_index');
            $table->dropIndex('activity_logs_source_created_at_index');
            $table->dropIndex('activity_logs_session_id_index');
            $table->dropIndex('activity_logs_s3_archived_at_index');

            // Supprimer les nouvelles colonnes
            $table->dropColumn([
                'actor_type',
                'source',
                'page_url',
                'session_id',
                'metadata',
                's3_key',
                's3_archived_at',
            ]);

            // Supprimer la FK avant renommage
            $table->dropForeign(['user_id']);
        });

        // Renommer user_id -> admin_id
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->renameColumn('user_id', 'admin_id');
        });

        // Renommer la table
        Schema::rename('activity_logs', 'admin_activity_logs');

        // Remettre la FK originale
        Schema::table('admin_activity_logs', function (Blueprint $table) {
            $table->foreign('admin_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
