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
            $table->boolean('plus_one')->default(false)->after('rsvp_status');
            $table->string('plus_one_name')->nullable()->after('plus_one');
            $table->text('dietary_restrictions')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn(['plus_one', 'plus_one_name', 'dietary_restrictions']);
        });
    }
};
