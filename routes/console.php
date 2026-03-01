<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:generate-recurring-transactions')->daily();
Schedule::command('backup:run --only-db --disable-notifications --isolated')->dailyAt('02:00');
Schedule::command('backup:clean --disable-notifications')->dailyAt('02:30');
Schedule::command('backup:monitor --isolated')->dailyAt('03:00');
