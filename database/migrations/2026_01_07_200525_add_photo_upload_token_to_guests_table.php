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
        Schema::table('guests', function (Blueprint $table) {
            $table->string('photo_upload_token', 64)->unique()->nullable()->after('invitation_token');
            $table->index('photo_upload_token');
        });

        // Generate tokens for existing checked-in guests
        $guests = \App\Models\Guest::where('checked_in', true)
            ->whereNull('photo_upload_token')
            ->get();
        foreach ($guests as $guest) {
            $guest->photo_upload_token = \Illuminate\Support\Str::random(64);
            $guest->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['photo_upload_token']);
            $table->dropColumn('photo_upload_token');
        });
    }
};
