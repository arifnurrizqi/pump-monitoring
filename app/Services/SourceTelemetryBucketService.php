<?php

namespace App\Services;

use App\Models\DataSource;
use App\Models\Device;
use App\Models\SourceInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class SourceTelemetryBucketService
{
    public function bucketStart(Carbon $time): string
    {
        $interval = max(60, (int) config('mqtt.history_interval_seconds', 60));
        if (3600 % $interval !== 0) {
            $interval = 60;
        }

        return Carbon::createFromTimestampUTC(intdiv($time->timestamp, $interval) * $interval)
            ->toDateTimeString();
    }

    /** @param array<string, mixed>|null $bucket
     *  @param array<string, array{value: float|null, quality: string}> $readings
     *  @return array<string, mixed>
     */
    public function accumulate(?array $bucket, string $start, Carbon $time, array $readings): array
    {
        $bucket ??= ['start' => $start, 'sample_count' => 0, 'metrics' => []];
        $bucket['sample_count']++;
        $bucket['last_recorded_at'] = $time->toIso8601String();

        foreach ($readings as $key => $reading) {
            $metric = $bucket['metrics'][$key] ?? [
                'sum' => 0.0, 'count' => 0, 'min' => null, 'max' => null,
                'last' => null, 'quality' => 'unknown', 'recorded_at' => null,
            ];

            if ($reading['quality'] === 'good' && $reading['value'] !== null) {
                $value = $reading['value'];
                $metric['sum'] += $value;
                $metric['count']++;
                $metric['min'] = $metric['min'] === null ? $value : min($metric['min'], $value);
                $metric['max'] = $metric['max'] === null ? $value : max($metric['max'], $value);
                $metric['last'] = $value;
            } else {
                $metric['last'] = null;
            }
            $metric['quality'] = $reading['quality'];
            $metric['recorded_at'] = $time->toIso8601String();
            $bucket['metrics'][$key] = $metric;
        }

        return $bucket;
    }

    /** @param array<string, mixed> $bucket */
    public function persist(int $sourceId, array $bucket, bool $mergeExisting = true): void
    {
        $existing = $mergeExisting ? SourceInterval::where('data_source_id', $sourceId)
            ->where('bucket_start', $bucket['start'])->first() : null;
        if ($existing) {
            $incomingIsLatest = Carbon::parse($bucket['last_recorded_at'])
                ->greaterThanOrEqualTo($existing->last_recorded_at);
            foreach ($existing->metrics as $key => $old) {
                if (! isset($bucket['metrics'][$key])) {
                    $bucket['metrics'][$key] = $old;
                    continue;
                }
                $new = $bucket['metrics'][$key];
                $newMetricIsLatest = ! isset($old['recorded_at']) || Carbon::parse($new['recorded_at'])
                    ->greaterThanOrEqualTo(Carbon::parse($old['recorded_at']));
                $bucket['metrics'][$key] = [
                    'sum' => ($old['sum'] ?? 0) + ($new['sum'] ?? 0),
                    'count' => ($old['count'] ?? 0) + ($new['count'] ?? 0),
                    'min' => $old['min'] === null ? $new['min'] : ($new['min'] === null ? $old['min'] : min($old['min'], $new['min'])),
                    'max' => $old['max'] === null ? $new['max'] : ($new['max'] === null ? $old['max'] : max($old['max'], $new['max'])),
                    'last' => $newMetricIsLatest ? $new['last'] : $old['last'],
                    'quality' => $newMetricIsLatest ? $new['quality'] : $old['quality'],
                    'recorded_at' => $newMetricIsLatest ? $new['recorded_at'] : $old['recorded_at'],
                ];
            }
            $bucket['sample_count'] += $existing->sample_count;
            if (! $incomingIsLatest) {
                $bucket['last_recorded_at'] = $existing->last_recorded_at->toIso8601String();
            }
        }
        SourceInterval::updateOrCreate(
            ['data_source_id' => $sourceId, 'bucket_start' => $bucket['start']],
            [
                'last_recorded_at' => Carbon::parse($bucket['last_recorded_at']),
                'sample_count' => $bucket['sample_count'],
                'metrics' => $bucket['metrics'],
            ],
        );
    }

    /** @param array<string, array{value: float|null, quality: string}> $readings */
    public function persistLate(DataSource $source, Carbon $time, array $readings): void
    {
        $start = $this->bucketStart($time);
        $existing = SourceInterval::where('data_source_id', $source->id)
            ->where('bucket_start', $start)->first();
        $bucket = $existing ? [
            'start' => $start,
            'sample_count' => $existing->sample_count,
            'last_recorded_at' => $existing->last_recorded_at->toIso8601String(),
            'metrics' => $existing->metrics,
        ] : null;
        $lastRecorded = $bucket ? Carbon::parse($bucket['last_recorded_at']) : null;
        $bucket = $this->accumulate($bucket, $start, $time, $readings);
        if ($lastRecorded && $lastRecorded->greaterThan($time)) {
            $bucket['last_recorded_at'] = $lastRecorded->toIso8601String();
            foreach ($readings as $key => $_reading) {
                if (isset($existing->metrics[$key])) {
                    $metric = $bucket['metrics'][$key];
                    $metric['last'] = $existing->metrics[$key]['last'];
                    $metric['quality'] = $existing->metrics[$key]['quality'];
                    $metric['recorded_at'] = $existing->metrics[$key]['recorded_at'] ?? $lastRecorded->toIso8601String();
                    $bucket['metrics'][$key] = $metric;
                }
            }
        }
        $this->persist($source->id, $bucket, false);
    }

    public function flushClosed(): void
    {
        $cache = Cache::store(config('mqtt.live_cache_store'));

        Device::query()->select('id', 'last_seen_at')->chunkById(100, function ($devices) use ($cache): void {
            foreach ($devices as $device) {
                $key = "telemetry:v2:{$device->id}";
                $cache->lock("{$key}:lock", 15)->block(5, function () use ($cache, $key, $device): void {
                    $state = $cache->get($key);
                    if (! $state) {
                        return;
                    }

                    $changed = false;
                    foreach ($state['sources'] as $sourceId => &$sourceState) {
                        if (! $sourceState['bucket']
                            || $sourceState['bucket']['start'] >= $this->bucketStart(now())) {
                            continue;
                        }
                        $this->persist((int) $sourceId, $sourceState['bucket']);
                        $sourceState['bucket'] = null;
                        $changed = true;
                    }
                    unset($sourceState);

                    if ($changed) {
                        $cache->forever($key, $state);
                    }
                    $lastSeen = collect($state['sources'])->pluck('last_seen_at')->filter()->max();
                    if ($lastSeen && (! $device->last_seen_at || Carbon::parse($lastSeen)->greaterThan($device->last_seen_at))) {
                        $device->update(['last_seen_at' => $lastSeen]);
                    }
                });
            }
        });
    }
}
