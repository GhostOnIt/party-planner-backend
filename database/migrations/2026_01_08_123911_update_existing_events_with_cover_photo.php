<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update existing events that have a featured photo to set cover_photo_id.
     */
    public function up(): void
    {
        // Mettre à jour les événements qui ont une photo featured
        // Note: SQLite ne supporte pas l'alias dans UPDATE (UPDATE events e ...), donc on évite les alias ici.
        $driver = DB::getDriverName();
        $true = $driver === 'pgsql' ? 'true' : '1';

        DB::statement("
            UPDATE events
            SET cover_photo_id = (
                SELECT id
                FROM photos
                WHERE photos.event_id = events.id
                  AND photos.is_featured = {$true}
                ORDER BY photos.created_at DESC
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1
                FROM photos
                WHERE photos.event_id = events.id
                  AND photos.is_featured = {$true}
            )
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Pas besoin de rollback pour les données
        // On peut simplement remettre cover_photo_id à null si nécessaire
        DB::table('events')->update(['cover_photo_id' => null]);
    }
};
