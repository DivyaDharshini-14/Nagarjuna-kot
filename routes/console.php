<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('database:backup')->dailyAt(config('backup.time', '03:00'))
    ->withoutOverlapping()->when(fn () => (bool) config('backup.enabled'));
