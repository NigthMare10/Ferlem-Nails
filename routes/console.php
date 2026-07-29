<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('studio:process-payroll')->dailyAt('00:15')->timezone('America/Tegucigalpa')->withoutOverlapping();
Schedule::command('studio:dispatch-daily-close-email')->everyMinute()->timezone('America/Tegucigalpa')->withoutOverlapping(5);
