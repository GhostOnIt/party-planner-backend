<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->integer('max_guests_allowed')->nullable()->after('expected_guests_count');
            $table->integer('max_collaborators_allowed')->nullable()->after('max_guests_allowed');
            $table->integer('max_photos_allowed')->nullable()->after('max_collaborators_allowed');
            $table->json('features_enabled')->nullable()->after('max_photos_allowed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['max_guests_allowed', 'max_collaborators_allowed', 'max_photos_allowed', 'features_enabled']);
        });
    }
};

