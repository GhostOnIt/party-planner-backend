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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('otp_enabled')->default(false)->after('is_active');
            $table->enum('preferred_otp_channel', ['email', 'sms', 'whatsapp'])->default('email')->after('otp_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_enabled', 'preferred_otp_channel']);
        });
    }
};
