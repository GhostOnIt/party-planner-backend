<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the event status update command to run daily at midnight
Schedule::command('events:update-statuses')
    ->daily()
    ->at('00:00')
    ->timezone('UTC');
