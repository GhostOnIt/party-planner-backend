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
        DB::statement("
            UPDATE events e
            SET cover_photo_id = (
                SELECT id 
                FROM photos p 
                WHERE p.event_id = e.id 
                AND p.is_featured = true 
                ORDER BY p.created_at DESC 
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1 
                FROM photos p 
                WHERE p.event_id = e.id 
                AND p.is_featured = true
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
