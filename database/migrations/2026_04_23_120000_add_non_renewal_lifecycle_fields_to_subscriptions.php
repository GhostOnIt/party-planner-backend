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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('renewal_reminder_sent_at')->nullable()->after('starts_at');
            $table->timestamp('final_reminder_sent_at')->nullable()->after('renewal_reminder_sent_at');
            $table->timestamp('grace_started_at')->nullable()->after('final_reminder_sent_at');
            $table->timestamp('archived_at')->nullable()->after('grace_started_at');
            $table->string('non_renewal_reason')->nullable()->after('archived_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'renewal_reminder_sent_at',
                'final_reminder_sent_at',
                'grace_started_at',
                'archived_at',
                'non_renewal_reason',
            ]);
        });
    }
};

