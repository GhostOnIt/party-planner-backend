<?php

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
