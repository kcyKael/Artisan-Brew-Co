<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('abc:emails-daily-summary')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('abc:emails-stock-digest')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('abc:emails-recover-outbox')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('abc:emails-prune-payloads')
    ->dailyAt('02:30')
    ->withoutOverlapping();
