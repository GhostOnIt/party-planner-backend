<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align DB with Subscription::cancel() which sets payment_status to cancelled.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_payment_status_check');
            DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_payment_status_check CHECK ("payment_status" in (\'pending\', \'paid\', \'failed\', \'refunded\', \'cancelled\'))');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY payment_status ENUM('pending', 'paid', 'failed', 'refunded', 'cancelled') NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'sqlite') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'cancelled'])->default('pending')->change();
            });
        }
    }

    /**
     * @throws \RuntimeException If any subscription still has payment_status cancelled.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (DB::table('subscriptions')->where('payment_status', 'cancelled')->exists()) {
            throw new \RuntimeException('Cannot rollback: subscriptions still have payment_status = cancelled.');
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS subscriptions_payment_status_check');
            DB::statement('ALTER TABLE subscriptions ADD CONSTRAINT subscriptions_payment_status_check CHECK ("payment_status" in (\'pending\', \'paid\', \'failed\', \'refunded\'))');
        } elseif ($driver === 'mysql') {
            DB::statement("ALTER TABLE subscriptions MODIFY payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
        } elseif ($driver === 'sqlite') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->change();
            });
        }
    }
};
