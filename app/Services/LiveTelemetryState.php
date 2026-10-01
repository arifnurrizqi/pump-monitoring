<?php

namespace App\Services;

use App\Models\Device;
use App\Models\TelemetryLatest;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LiveTelemetryState
{
    /** @return array<string, mixed>|null */
    public function apply(Device $device): ?array
    {
        try {
            $state = Cache::store(config('mqtt.live_cache_store'))
                ->get("telemetry:live:{$device->id}");
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! $state) {
            return null;
        }

        $latest = new TelemetryLatest($state['latest']);
        $latest->device_id = $device->id;
        $device->setRelation('latestData', $latest);
        $device->setRelation('liveTelemetryState', $state);

        return $state;
    }

    public function usage(array $state, string $period): ?float
    {
        $key = $period === 'month' ? 'usage_month_key' : 'usage_day_key';
        $expected = $period === 'month'
            ? now()->startOfMonth()->toDateString()
            : now()->startOfDay()->toDateString();

        if (($state[$key] ?? null) !== $expected) {
            return null;
        }

        return (float) $state[$period === 'month' ? 'usage_month' : 'usage_today'];
    }
}
