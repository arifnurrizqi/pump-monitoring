<?php

namespace App\Services;

use App\Models\Device;
use App\Models\TelemetryLatest;
use App\Models\TelemetryRaw;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class TelemetryBucketService
{
    public function bucketStart(Carbon $recordedAt): string
    {
        $interval = max(60, (int) config('mqtt.history_interval_seconds', 60));
        if (3600 % $interval !== 0) {
            $interval = 60;
        }

        return Carbon::createFromTimestampUTC(intdiv($recordedAt->timestamp, $interval) * $interval)
            ->toDateTimeString();
    }

    /** @param array<string, mixed>|null $bucket
     *  @param array<string, mixed> $values
     *  @return array<string, mixed>
     */
    public function accumulate(?array $bucket, string $start, TelemetryRaw $telemetry, array $values): array
    {
        $bucket ??= [
            'start' => $start,
            'count' => 0,
            'pulse' => 0,
            'usage' => 0.0,
            'metrics' => [],
        ];

        $bucket['count']++;
        $bucket['pulse'] += (int) $telemetry->delta_pulse;
        $bucket['usage'] += (float) $telemetry->usage_liter;
        $bucket['last_at'] = $telemetry->recorded_at->toIso8601String();
        $bucket['status_code'] = $telemetry->status_code;
        $bucket['alarm_code'] = $telemetry->alarm_code;
        $bucket['valve_state'] = $telemetry->valve_state;
        $bucket['pulse_per_liter_used'] = (float) $telemetry->pulse_per_liter_used;

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $number = (float) $value;
            $metric = $bucket['metrics'][$key] ?? [
                'sum' => 0.0, 'count' => 0, 'min' => $number, 'max' => $number, 'last' => $number,
            ];
            $metric['sum'] += $number;
            $metric['count']++;
            $metric['min'] = min($metric['min'], $number);
            $metric['max'] = max($metric['max'], $number);
            $metric['last'] = $number;
            $bucket['metrics'][$key] = $metric;
        }

        return $bucket;
    }

    /** @param array<string, mixed> $bucket */
    public function persist(Device $device, array $bucket, TelemetryLatest $latest): void
    {
        $metrics = $bucket['metrics'];
        $averages = [];
        foreach ($metrics as $key => $metric) {
            $averages[$key] = $metric['sum'] / $metric['count'];
        }

        TelemetryRaw::updateOrCreate(
            ['device_id' => $device->id, 'bucket_start' => $bucket['start']],
            $averages + [
                'recorded_at' => Carbon::parse($bucket['last_at']),
                'delta_pulse' => $bucket['pulse'],
                'usage_liter' => $bucket['usage'],
                'pulse_per_liter_used' => $bucket['pulse_per_liter_used'],
                'sample_count' => $bucket['count'],
                'status_code' => $bucket['status_code'],
                'alarm_code' => $bucket['alarm_code'],
                'valve_state' => $bucket['valve_state'],
                'raw_payload' => ['aggregation' => 'interval', 'metrics' => $metrics],
            ],
        );

        $latestValues = array_intersect_key($latest->getAttributes(), array_flip($latest->getFillable()));
        TelemetryLatest::updateOrCreate(['device_id' => $device->id], $latestValues);
        $device->update(['last_seen_at' => $latest->last_seen_at]);
    }

    public function flushClosedBuckets(): void
    {
        $cache = Cache::store(config('mqtt.live_cache_store'));

        Device::query()->select('id')->chunkById(100, function ($devices) use ($cache): void {
            foreach ($devices as $device) {
                $key = "telemetry:live:{$device->id}";

                $cache->lock("{$key}:lock", 15)->block(5, function () use ($cache, $key, $device): void {
                    $state = $cache->get($key);

                    if (! $state || ! $state['bucket']
                        || $state['bucket']['start'] >= $this->bucketStart(now())) {
                        return;
                    }

                    $latest = new TelemetryLatest($state['latest']);
                    $latest->device_id = $device->id;
                    $this->persist($device, $state['bucket'], $latest);
                    $state['bucket'] = null;
                    $cache->forever($key, $state);
                });
            }
        });
    }
}
