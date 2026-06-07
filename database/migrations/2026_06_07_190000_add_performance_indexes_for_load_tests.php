<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->index(['user_id', 'date'], 'events_user_id_date_idx');
            $table->index(['user_id', 'status', 'date'], 'events_user_id_status_date_idx');
        });

        Schema::table('collaborators', function (Blueprint $table) {
            $table->index(['user_id', 'accepted_at', 'event_id'], 'collaborators_user_accepted_event_idx');
        });

        Schema::table('event_creation_invitations', function (Blueprint $table) {
            $table->index(['email', 'event_id'], 'event_creation_invitations_email_event_idx');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->index(['event_id', 'rsvp_status'], 'guests_event_id_rsvp_status_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['event_id', 'status'], 'tasks_event_id_status_idx');
            $table->index(['event_id', 'due_date'], 'tasks_event_id_due_date_idx');
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->index('event_id', 'budget_items_event_id_idx');
            $table->index(['event_id', 'paid'], 'budget_items_event_id_paid_idx');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index('event_id', 'subscriptions_event_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex('subscriptions_event_id_idx');
        });

        Schema::table('budget_items', function (Blueprint $table) {
            $table->dropIndex('budget_items_event_id_paid_idx');
            $table->dropIndex('budget_items_event_id_idx');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_event_id_due_date_idx');
            $table->dropIndex('tasks_event_id_status_idx');
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex('guests_event_id_rsvp_status_idx');
        });

        Schema::table('event_creation_invitations', function (Blueprint $table) {
            $table->dropIndex('event_creation_invitations_email_event_idx');
        });

        Schema::table('collaborators', function (Blueprint $table) {
            $table->dropIndex('collaborators_user_accepted_event_idx');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex('events_user_id_status_date_idx');
            $table->dropIndex('events_user_id_date_idx');
        });
    }
};
