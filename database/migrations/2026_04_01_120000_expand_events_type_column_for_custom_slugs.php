<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les types d'événement personnalisés (user_event_types.slug) ne sont pas dans l'enum d'origine.
     * PostgreSQL applique la contrainte CHECK events_type_check sur les colonnes enum Laravel.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE events DROP CONSTRAINT IF EXISTS events_type_check');
            DB::statement('ALTER TABLE events ALTER COLUMN type TYPE VARCHAR(255)');
            DB::statement("ALTER TABLE events ALTER COLUMN type SET DEFAULT 'autre'");

            return;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE events MODIFY COLUMN type VARCHAR(255) NOT NULL DEFAULT 'autre'");

            return;
        }

        Schema::table('events', function (Blueprint $table) {
            $table->string('type', 255)->default('autre')->change();
        });
    }

    /**
     * Rollback volontairement non implémenté : rétablir l'enum PostgreSQL casserait les événements
     * dont le type est un slug personnalisé.
     */
    public function down(): void
    {
        //
    }
};
