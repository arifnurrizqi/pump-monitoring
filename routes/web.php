<?php

use App\Http\Controllers\AlarmController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataloggerController;
use App\Http\Controllers\DeviceCommandController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DeviceSettingController;
use App\Http\Controllers\MonitoringConfigurationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemMqttSettingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::resource('devices', DeviceController::class);

    Route::get('/devices/{device}/monitoring', [MonitoringConfigurationController::class, 'index'])->name('devices.monitoring.index');
    Route::post('/devices/{device}/monitoring/site', [MonitoringConfigurationController::class, 'site'])->name('devices.monitoring.site');
    Route::post('/devices/{device}/monitoring/assets', [MonitoringConfigurationController::class, 'asset'])->name('devices.monitoring.asset');
    Route::post('/devices/{device}/monitoring/sources', [MonitoringConfigurationController::class, 'source'])->name('devices.monitoring.source');
    Route::post('/devices/{device}/monitoring/sources/{source}/parameters', [MonitoringConfigurationController::class, 'parameter'])->name('devices.monitoring.parameter');
    Route::patch('/devices/{device}/monitoring/sources/{source}/parameters/{parameter}', [MonitoringConfigurationController::class, 'updateParameter'])->name('devices.monitoring.parameter.update');

    Route::post('/devices/{device}/share', [DeviceController::class, 'share'])->name('devices.share');
    Route::patch('/devices/{device}/access/{user}', [DeviceController::class, 'updateAccess'])->name('devices.access.update');
    Route::delete('/devices/{device}/access/{user}', [DeviceController::class, 'removeAccess'])->name('devices.access.remove');

    Route::post('/devices/{device}/valve', [DeviceCommandController::class, 'storeValveCommand'])
        ->name('devices.valve.store');
    Route::get('/devices/{device}/valve/commands/{command}', [DeviceCommandController::class, 'showValveCommand'])
        ->name('devices.valve.commands.show');

    Route::get('/devices/{device}/settings/edit', [DeviceSettingController::class, 'edit'])
        ->name('devices.settings.edit');

    Route::put('/devices/{device}/settings', [DeviceSettingController::class, 'update'])
        ->name('devices.settings.update');

    Route::get('/alarms', [AlarmController::class, 'index'])->name('alarms.index');
    Route::patch('/alarms/{alarm}/acknowledge', [AlarmController::class, 'acknowledge'])->name('alarms.ack');

    Route::get('/datalogger', [DataloggerController::class, 'index'])->name('datalogger.index');
    Route::get('/datalogger/export', [DataloggerController::class, 'export'])->name('datalogger.export');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/settings/mqtt', [SystemMqttSettingController::class, 'edit'])
        ->name('settings.mqtt.edit');
    Route::put('/settings/mqtt', [SystemMqttSettingController::class, 'update'])
        ->name('settings.mqtt.update');
});

require __DIR__.'/auth.php';
