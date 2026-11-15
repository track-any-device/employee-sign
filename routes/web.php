<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\DeviceCommandController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceEmployeesController;
use App\Http\Controllers\DevicePairingController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeMergeController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

/*
| Tenant-less ("null tenant") app routes. When tenancy arrives, the same
| group is mounted again under /{tenant}/ with a middleware that checks
| membership and sets App\Support\CurrentTenant.
*/
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('devices', DeviceController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('devices/{device}/pairing-code', [DevicePairingController::class, 'store'])->middleware('throttle:20,1')->name('devices.pairing.store');
    Route::delete('devices/{device}/token', [DevicePairingController::class, 'destroy'])->name('devices.token.destroy');
    Route::post('devices/{device}/commands', [DeviceCommandController::class, 'store'])->middleware('throttle:30,1')->name('devices.commands.store');
    Route::put('devices/{device}/employees', [DeviceEmployeesController::class, 'update'])->name('devices.employees.update');

    Route::get('employees/merge', [EmployeeMergeController::class, 'create'])->name('employees.merge.create');
    Route::post('employees/merge', [EmployeeMergeController::class, 'store'])->name('employees.merge.store');
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('sites', [SiteController::class, 'store'])->name('sites.store');
    Route::delete('sites/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
    Route::post('sites/{site}/locations', [SiteController::class, 'storeLocation'])->name('sites.locations.store');
    Route::delete('sites/{site}/locations/{location}', [SiteController::class, 'destroyLocation'])->name('sites.locations.destroy');

    // Spelled as in the brief ("attandance"); /attendance redirects to it.
    Route::get('attandance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::redirect('attendance', '/attandance');

    Route::get('members', [MemberController::class, 'index'])->name('members.index');
});

require __DIR__.'/settings.php';
