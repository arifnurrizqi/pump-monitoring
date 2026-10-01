<?php

namespace App\Observers;

use App\Models\TelemetryRaw;
use App\Services\ParameterHealthService;

class TelemetryObserver
{
    public function created(TelemetryRaw $telemetry)
    {
        $health = app(ParameterHealthService::class)
            ->evaluate($telemetry);

        // simpan ke memory sementara (optional)
        cache()->put(
            "device_health_{$telemetry->device_id}",
            $health,
            now()->addMinutes(5)
        );
    }
}