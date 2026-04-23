<?php

use App\Jobs\ArchiveAndPurgeLogsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the event status update command to run every 6 hours
Schedule::command('events:update-statuses')
    ->everySixHours()
    ->timezone('UTC');

// Archiver et purger les logs d'activité de plus de 30 jours (tous les jours à 02h00 UTC)
Schedule::job(new ArchiveAndPurgeLogsJob(retentionDays: 30, batchSize: 500))
    ->dailyAt('02:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

// Expirer automatiquement les paiements pending trop anciens.
Schedule::command('payments:expire-pending --minutes=10')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer();

// Cycle de vie non-renouvellement des abonnements compte.
Schedule::command('subscriptions:send-renewal-reminders')
    ->dailyAt('08:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:apply-non-renewal-restrictions')
    ->hourly()
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:archive-exceeding-resources --after-days=90')
    ->dailyAt('03:00')
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('subscriptions:restore-access-after-renewal')
    ->hourly()
    ->timezone('UTC')
    ->withoutOverlapping()
    ->onOneServer();
