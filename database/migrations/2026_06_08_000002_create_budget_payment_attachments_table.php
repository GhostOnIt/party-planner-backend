<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_payment_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_item_payment_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('budget_item_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('event_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('s3_path');
            $table->timestamps();

            $table->index(['event_id', 'budget_item_id']);
            $table->index('budget_item_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_payment_attachments');
    }
};
