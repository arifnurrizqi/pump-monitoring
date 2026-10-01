<?php

namespace App\Services;

use App\Events\SourceTelemetryUpdated;
use App\Models\DataSource;
use App\Models\Device;
use App\Models\SourceInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MqttParameterIngestionService
{
    public function __construct(
        private readonly SourceTelemetryBucketService $buckets,
        private readonly SourceParameterAlarmService $alarms,
    ) {}

    /** @param array<string, mixed> $payload */
    public function ingest(array $payload): string
    {
        $validator = Validator::make($payload, [
            'schema_version' => ['required', 'integer', 'in:2'],
            'device_sn' => ['required', 'string', 'max:100'],
            'message_id' => ['required', 'string', 'max:100'],
            'recorded_at' => ['required', 'date'],
            'sources' => ['required', 'array', 'min:1', 'max:30'],
            'sources.*.source' => ['required', 'string', 'max:60'],
            'sources.*.values' => ['required', 'array', 'min:1'],
            'sources.*.quality' => ['nullable', 'array'],
        ]);
        if ($validator->fails()) {
            return 'invalid';
        }

        if (! array_is_list($payload['sources'])) {
            return 'invalid';
        }

        $device = Device::where('device_sn', $payload['device_sn'])->first();
        if (! $device || ! $device->is_active) {
            return 'unknown_device';
        }

        $recordedAt = Carbon::parse($payload['recorded_at']);
        if ($recordedAt->greaterThan(now()->addMinutes(5))) {
            return 'future_timestamp';
        }

        $registered = $device->sources()->with('parameters')->where('is_active', true)
            ->get()->keyBy('source_key');
        $normalized = [];
        foreach ($payload['sources'] as $item) {
            if (! is_array($item) || ! is_string($item['source'] ?? null)
                || ! is_array($item['values'] ?? null) || array_is_list($item['values'])
                || isset($normalized[$item['source']])) {
                return 'invalid';
            }

            $source = $registered->get($item['source']);
            if (! $source) {
                return 'unknown_source';
            }

            $parameters = $source->parameters->keyBy('key');
            $qualityMap = $item['quality'] ?? [];
            if (! is_array($qualityMap) || ($qualityMap !== [] && array_is_list($qualityMap))) {
                return 'invalid';
            }
            foreach (array_keys($qualityMap) as $key) {
                if (! array_key_exists($key, $item['values'])) {
                    return 'invalid';
                }
            }

            $readings = [];
            foreach ($item['values'] as $key => $value) {
                $parameter = $parameters->get($key);
                $quality = $qualityMap[$key] ?? 'good';
                if (! $parameter || ! in_array($quality, ['good', 'uncertain', 'error'], true)
                    || ($value !== null && ! is_int($value) && ! is_float($value))
                    || ($value === null && $quality === 'good')) {
                    return 'invalid_parameter';
                }
                $readings[$key] = [
                    'parameter' => $parameter,
                    'value' => $value === null ? null : (float) $value,
                    'quality' => $quality,
                ];
            }
            $normalized[$item['source']] = ['source' => $source, 'readings' => $readings];
        }

        $cache = Cache::store(config('mqtt.live_cache_store'));
        $key = "telemetry:v2:{$device->id}";

        return $cache->lock("{$key}:lock", 15)->block(5, function () use (
            $cache, $key, $device, $payload, $normalized, $recordedAt
        ): string {
            $state = $cache->get($key) ?? ['seen_ids' => [], 'sources' => []];
            if (in_array($payload['message_id'], $state['seen_ids'], true)) {
                return 'duplicate';
            }

            $updates = [];
            foreach ($normalized as $sourceKey => $item) {
                /** @var DataSource $source */
                $source = $item['source'];
                $sourceState = $state['sources'][$source->id] ?? $this->initialSourceState($source);
                $readings = [];
                foreach ($item['readings'] as $parameterKey => $reading) {
                    $readings[$parameterKey] = [
                        'value' => $reading['value'], 'quality' => $reading['quality'],
                    ];
                }

                $bucketStart = $this->buckets->bucketStart($recordedAt);
                $lastTime = $sourceState['last_recorded_at']
                    ? Carbon::parse($sourceState['last_recorded_at']) : null;
                if ($lastTime && $recordedAt->lessThan($lastTime)) {
                    if ($sourceState['bucket'] && $sourceState['bucket']['start'] === $bucketStart) {
                        $original = $sourceState['bucket'];
                        $sourceState['bucket'] = $this->buckets->accumulate(
                            $original, $bucketStart, $recordedAt, $readings,
                        );
                        $sourceState['bucket']['last_recorded_at'] = $original['last_recorded_at'];
                        foreach ($readings as $parameterKey => $_reading) {
                            if (isset($original['metrics'][$parameterKey])) {
                                $sourceState['bucket']['metrics'][$parameterKey]['last'] =
                                    $original['metrics'][$parameterKey]['last'];
                                $sourceState['bucket']['metrics'][$parameterKey]['quality'] =
                                    $original['metrics'][$parameterKey]['quality'];
                                $sourceState['bucket']['metrics'][$parameterKey]['recorded_at'] =
                                    $original['metrics'][$parameterKey]['recorded_at'] ?? $lastTime->toIso8601String();
                            }
                        }
                    } else {
                        $this->buckets->persistLate($source, $recordedAt, $readings);
                    }
                    $state['sources'][$source->id] = $sourceState;
                    continue;
                }

                if ($sourceState['bucket'] && $sourceState['bucket']['start'] !== $bucketStart) {
                    $this->buckets->persist($source->id, $sourceState['bucket']);
                    $sourceState['bucket'] = null;
                }

                foreach ($item['readings'] as $parameterKey => $reading) {
                    $sourceState['latest'][$parameterKey] = [
                        'value' => $reading['value'],
                        'quality' => $reading['quality'],
                        'recorded_at' => $recordedAt->toIso8601String(),
                    ];
                    $this->alarms->evaluate(
                        $device, $reading['parameter'], $reading['value'],
                        $reading['quality'], $sourceState['alarm_counts'],
                    );
                }
                $sourceState['last_recorded_at'] = $recordedAt->toIso8601String();
                $sourceState['last_seen_at'] = now()->toIso8601String();
                $sourceState['bucket'] = $this->buckets->accumulate(
                    $sourceState['bucket'], $bucketStart, $recordedAt, $readings,
                );
                $state['sources'][$source->id] = $sourceState;
                $updates[] = [
                    'source_id' => $source->id,
                    'source_key' => $sourceKey,
                    'recorded_at' => $recordedAt->toIso8601String(),
                    'received_at' => $sourceState['last_seen_at'],
                    'readings' => $readings,
                ];
            }

            $state['seen_ids'][] = $payload['message_id'];
            $state['seen_ids'] = array_slice($state['seen_ids'], -2000);
            $cache->forever($key, $state);

            if ($updates) {
                try {
                    SourceTelemetryUpdated::dispatch(
                        $device->id, $updates, $device->activeAlarms()->count(),
                    );
                } catch (Throwable $exception) {
                    report($exception);
                }
            }

            return 'accepted';
        });
    }

    /** @return array<string, mixed> */
    private function initialSourceState(DataSource $source): array
    {
        $interval = SourceInterval::where('data_source_id', $source->id)
            ->orderByDesc('bucket_start')->first();
        $latest = [];
        if ($interval) {
            foreach ($interval->metrics as $key => $metric) {
                $latest[$key] = [
                    'value' => $metric['last'] ?? null,
                    'quality' => $metric['quality'] ?? 'unknown',
                    'recorded_at' => $metric['recorded_at'] ?? $interval->last_recorded_at->toIso8601String(),
                ];
            }
        }

        $counts = [];
        foreach ($source->parameters as $parameter) {
            $active = $source->device->alarmLogs()
                ->where('source_parameter_id', $parameter->id)
                ->whereIn('status', ['active', 'acknowledged'])->first();
            if ($active) {
                $counts[$parameter->id] = str_ends_with($active->alarm_code, '_high') ? 3 : -3;
            }
        }

        return [
            'latest' => $latest,
            'bucket' => null,
            'alarm_counts' => $counts,
            'last_recorded_at' => $interval?->last_recorded_at?->toIso8601String(),
            'last_seen_at' => null,
        ];
    }
}
