<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Programar sincronización automática de transacciones
Schedule::command('binance:sync-transactions --days=1')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Sincronización completa semanal
Schedule::command('binance:sync-transactions --days=7')
    ->weeklyOn(1, '02:00') // Lunes a las 2 AM
    ->withoutOverlapping()
    ->runInBackground();
