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
