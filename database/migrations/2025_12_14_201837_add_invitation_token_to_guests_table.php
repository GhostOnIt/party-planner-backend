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
            $table->string('invitation_token', 64)->unique()->nullable()->after('invitation_sent_at');
        });

        // Generate tokens for existing guests
        $guests = \App\Models\Guest::whereNull('invitation_token')->get();
        foreach ($guests as $guest) {
            $guest->invitation_token = \Illuminate\Support\Str::random(64);
            $guest->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('invitation_token');
        });
    }
};
