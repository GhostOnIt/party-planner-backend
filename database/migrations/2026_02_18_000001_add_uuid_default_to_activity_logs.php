<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ajoute la génération automatique d'UUID pour la colonne id de activity_logs
     * si elle n'est pas déjà présente.
     */
    public function up(): void
    {
        // Vérifier si le default existe déjà, sinon l'ajouter
        $defaultExists = DB::selectOne("
            SELECT column_default 
            FROM information_schema.columns 
            WHERE table_name = 'activity_logs' 
            AND column_name = 'id' 
            AND column_default LIKE 'gen_random_uuid%'
        ");

        if (!$defaultExists) {
            DB::statement('ALTER TABLE activity_logs ALTER COLUMN id SET DEFAULT gen_random_uuid()');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Retirer le default (optionnel, car on ne peut pas vraiment "annuler" cette modification)
        DB::statement('ALTER TABLE activity_logs ALTER COLUMN id DROP DEFAULT');
    }
};
