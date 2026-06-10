<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_payment_method_check");
            DB::statement("ALTER TABLE payments ALTER COLUMN payment_method TYPE VARCHAR(255)");
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_method')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_payment_method_check CHECK (payment_method IN ('mtn_mobile_money', 'airtel_money'))");
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payment_method', ['mtn_mobile_money', 'airtel_money'])->change();
        });
    }
};
