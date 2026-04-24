<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tracking_code')->unique();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('current_stage_id')->nullable()->constrained('quote_request_stages')->nullOnDelete();
            $table->foreignUuid('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->string('outcome')->nullable();
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone', 30);
            $table->string('company_name');
            $table->text('business_needs');
            $table->unsignedInteger('budget_estimate')->nullable();
            $table->unsignedInteger('team_size')->nullable();
            $table->string('timeline')->nullable();
            $table->json('event_types')->nullable();
            $table->timestamp('call_scheduled_at')->nullable();
            $table->text('outcome_note')->nullable();
            $table->timestamp('last_stage_changed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
