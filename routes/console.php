<?php

use App\Jobs\ExpireDeviceCommands;
use App\Jobs\MarkStaleDevicesOffline;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new MarkStaleDevicesOffline)->everyMinute()->withoutOverlapping();
Schedule::job(new ExpireDeviceCommands)->everyMinute()->withoutOverlapping();
