<?php

use App\Http\Controllers\DeviceApi;
use App\Http\Middleware\AuthenticateDevice;
use Illuminate\Support\Facades\Route;

/*
| Device API — prefix /api/device/v1 (see bootstrap/app.php, docs/device-api.md).
| Stateless: no session or cookies. Devices authenticate with a bearer token.
*/

Route::post('claim', DeviceApi\ClaimController::class)
    ->middleware('throttle:device-claim')
    ->name('device-api.claim');

Route::middleware([AuthenticateDevice::class, 'throttle:device-api'])->name('device-api.')->group(function () {
    Route::get('me', DeviceApi\MeController::class)->name('me');
    Route::post('attendance', DeviceApi\AttendanceController::class)->name('attendance');
    Route::get('employees/changes', DeviceApi\EmployeeChangesController::class)->name('employees.changes');
    Route::post('employees/push', DeviceApi\EmployeePushController::class)->name('employees.push');
    Route::post('heartbeat', DeviceApi\HeartbeatController::class)->name('heartbeat');
    Route::post('broadcasting/auth', DeviceApi\BroadcastAuthController::class)->name('broadcasting.auth');
    Route::post('commands/{command}/result', DeviceApi\CommandResultController::class)->name('commands.result');
});
