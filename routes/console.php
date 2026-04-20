<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; // <--- 1. WAJIB TAMBAHKAN INI


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('attendance:generate-alpha-dynamic')->everyMinute();
Schedule::command('notifications:prune-hybrid')->dailyAt('02:00');
