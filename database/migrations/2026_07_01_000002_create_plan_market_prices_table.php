<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_market_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('country', 3);
            $table->string('currency', 3);
            $table->unsignedInteger('price')->default(0);
            $table->timestamps();

            $table->unique(['plan_id', 'country']);
        });

        $markets = [
            'COG' => 'XAF',
            'COD' => 'CDF',
            'CMR' => 'XAF',
            'GAB' => 'XAF',
            'SEN' => 'XOF',
            'CIV' => 'XOF',
        ];

        $now = now();
        $rows = [];
        foreach (DB::table('plans')->select('id', 'price')->get() as $plan) {
            foreach ($markets as $country => $currency) {
                $rows[] = [
                    'id' => (string) Str::uuid(),
                    'plan_id' => $plan->id,
                    'country' => $country,
                    'currency' => $currency,
                    'price' => (int) $plan->price,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('plan_market_prices')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_market_prices');
    }
};
