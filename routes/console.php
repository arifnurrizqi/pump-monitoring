<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('aggregate:hourly')
    ->cron('2 * * * *')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('telemetry:flush-buckets')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('aggregate:daily')
    ->dailyAt('00:10')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('aggregate:monthly')
    ->monthlyOn(1, '00:20')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('aggregate:yearly')
    ->yearlyOn(1, 1, '00:30')
    ->withoutOverlapping()
    ->runInBackground();
