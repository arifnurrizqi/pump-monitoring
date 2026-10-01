<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TelemetryController;

Route::prefix('v1')->group(function () {
  Route::post('/telemetry', [TelemetryController::class, 'store']);
});
