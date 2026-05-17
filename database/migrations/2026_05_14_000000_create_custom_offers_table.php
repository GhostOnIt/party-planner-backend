<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_offers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('price_amount');
            $table->string('price_currency', 10)->default('XAF');
            $table->json('features')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedInteger('validity_days')->default(30);
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('client_token', 64)->unique();
            $table->timestamp('client_responded_at')->nullable();
            $table->text('client_response_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_offers');
    }
};
