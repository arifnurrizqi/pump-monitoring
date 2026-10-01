<?php

namespace App\Services;

use App\Events\TelemetryUpdated;
use App\Jobs\ReaggregateLateTelemetry;
use App\Models\Device;
use App\Models\TelemetryLatest;
use App\Models\TelemetryRaw;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Throwable;

class MqttLiveIngestionService
{
    private const METRICS = [
        'ph', 'turbidity', 'sample_flow', 'level_distance_cm', 'level_cm',
        'level_percent', 'device_voltage_mcu', 'device_voltage_5v',
        'device_voltage_input', 'signal_strength',
    ];

    public function __construct(
        private readonly TankLevelService $tankLevel,
        private readonly ParameterHealthService $health,
        private readonly AlarmService $alarms,
        private readonly TelemetryRealtimeService $realtime,
        private readonly TelemetryBucketService $buckets,
    ) {}

    /** @param array<string, mixed> $payload */
    public function ingest(array $payload): string
    {
        $validator = Validator::make($payload, [
            'device_sn' => ['required', 'string', 'max:100'],
            'message_id' => ['required', 'string', 'max:100'],
            'recorded_at' => ['nullable', 'date'],
            'ph' => ['nullable', 'numeric'],
            'turbidity' => ['nullable', 'numeric'],
            'sample_flow' => ['nullable', 'numeric', 'min:0'],
            'level_distance_cm' => ['nullable', 'numeric', 'min:0'],
            'level_cm' => ['nullable', 'numeric', 'min:0'],
            'level_percent' => ['prohibited'],
            'delta_pulse' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'device_voltage_mcu' => ['nullable', 'numeric'],
            'device_voltage_5v' => ['nullable', 'numeric'],
            'device_voltage_input' => ['nullable', 'numeric'],
            'signal_strength' => ['nullable', 'integer'],
            'alarm_code' => ['nullable', 'string', 'max:50'],
            'valve_state' => ['nullable', 'boolean'],
            'command_id' => ['nullable', 'integer', 'min:1'],
            'flow' => ['prohibited'],
            'flow_total' => ['prohibited'],
        ]);
        $validator->after(function ($validator) use ($payload): void {
            if (array_key_exists('level_cm', $payload) && array_key_exists('level_distance_cm', $payload)) {
                $validator->errors()->add('level_cm', 'Kirim salah satu pengukuran level.');
            }
        });

        if ($validator->fails()) {
            return 'invalid';
        }

        $device = Device::where('device_sn', $payload['device_sn'])->first();

        if (! $device || ! $device->is_active) {
            return 'unknown_device';
        }

        $setting = $device->setting()->firstOrCreate([], [
            'pulse_per_liter' => 450,
            'alarm_enabled' => true,
            'sampling_interval_sec' => 10,
            'send_interval_sec' => 10,
            'timezone' => 'Asia/Jakarta',
        ]);
        $recordedAt = isset($payload['recorded_at']) ? Carbon::parse($payload['recorded_at']) : now();
        $receivedAt = now();
        if ($recordedAt->greaterThan($receivedAt->copy()->addMinutes(5))) {
            return 'future_timestamp';
        }
        $cache = Cache::store(config('mqtt.live_cache_store'));
        $key = "telemetry:live:{$device->id}";

        return $cache->lock("{$key}:lock", 15)->block(5, function () use (
            $cache, $key, $device, $setting, $payload, $recordedAt, $receivedAt
        ): string {
            $state = $cache->get($key) ?? $this->initialState($device);

            if (in_array($payload['message_id'], $state['seen_ids'], true)) {
                return 'duplicate';
            }

            $latest = new TelemetryLatest($state['latest']);
            $latest->device_id = $device->id;
            $lastRecorded = $latest->recorded_at;

            if ($lastRecorded && $recordedAt->lessThan($lastRecorded)) {
                return $this->storeLate($device, $setting, $payload, $recordedAt, $receivedAt, $latest, $state, $cache, $key);
            }

            $bucketStart = $this->buckets->bucketStart($recordedAt);

            if ($state['bucket'] && $state['bucket']['start'] !== $bucketStart) {
                $this->buckets->persist($device, $state['bucket'], $latest);
                $state['bucket'] = null;
            }

            $values = [];
            foreach (self::METRICS as $metric) {
                if (array_key_exists($metric, $payload)) {
                    $values[$metric] = $payload[$metric];
                }
            }

            if (array_key_exists('level_distance_cm', $payload) || array_key_exists('level_cm', $payload)) {
                $level = $this->tankLevel->calculate(
                    $payload['level_distance_cm'] ?? $payload['level_cm'],
                    $setting->tank_height_cm,
                );
                $values['level_distance_cm'] = $level['distance_cm'];
                $values['level_cm'] = $level['water_height_cm'];
                $values['level_percent'] = $level['percent'];
            }

            $deltaPulse = (int) ($payload['delta_pulse'] ?? 0);
            $pulsePerLiter = (float) $setting->pulse_per_liter;
            $usage = $pulsePerLiter > 0 ? $deltaPulse / $pulsePerLiter : 0.0;
            $latest->fill($values + [
                'total_pulse' => (int) $latest->total_pulse + $deltaPulse,
                'last_delta_pulse' => $deltaPulse,
                'last_usage_liter' => $usage,
                'total_usage_liter' => (float) $latest->total_usage_liter + $usage,
                'recorded_at' => $recordedAt,
                'last_seen_at' => $receivedAt,
            ]);

            foreach (['alarm_code', 'valve_state'] as $field) {
                if (array_key_exists($field, $payload)) {
                    $latest->{$field} = $payload[$field];
                }
            }

            $health = $this->health->evaluate($latest);
            $latest->status_code = $this->health->waterQuality($health);

            $telemetry = new TelemetryRaw($values + [
                'device_id' => $device->id,
                'message_id' => $payload['message_id'],
                'delta_pulse' => $deltaPulse,
                'usage_liter' => $usage,
                'pulse_per_liter_used' => $pulsePerLiter,
                'recorded_at' => $recordedAt,
                'status_code' => $latest->status_code,
                'alarm_code' => $latest->alarm_code,
                'valve_state' => $latest->valve_state,
            ]);

            $this->alarms->evaluateLive($telemetry, $setting, $state['alarm_counts']);
            $this->acknowledgeValve($device, $payload, $latest);
            $state['bucket'] = $this->buckets->accumulate($state['bucket'], $bucketStart, $telemetry, $values);
            $state['latest'] = $latest->getAttributes();
            $state['seen_ids'][] = $payload['message_id'];
            $state['seen_ids'] = array_slice($state['seen_ids'], -2000);

            $day = $receivedAt->copy()->startOfDay()->toDateString();
            $month = $receivedAt->copy()->startOfMonth()->toDateString();
            if ($state['usage_day_key'] !== $day) {
                $state['usage_day_key'] = $day;
                $state['usage_today'] = 0.0;
            }
            if ($state['usage_month_key'] !== $month) {
                $state['usage_month_key'] = $month;
                $state['usage_month'] = 0.0;
            }
            $state['usage_today'] += $usage;
            $state['usage_month'] += $usage;
            $cache->forever($key, $state);

            try {
                TelemetryUpdated::dispatch($device->id, $this->realtime->snapshot(
                    $device, $telemetry, $latest, $health,
                    $state['usage_today'], $state['usage_month'],
                ));
            } catch (Throwable $exception) {
                report($exception);
            }

            return 'accepted';
        });
    }

    private function initialState(Device $device): array
    {
        $latest = $device->latestData()->first() ?? new TelemetryLatest([
            'device_id' => $device->id,
            'total_pulse' => 0,
            'total_usage_liter' => 0,
        ]);
        $alarmCounts = [];
        foreach ($device->alarmLogs()->whereIn('status', ['active', 'acknowledged'])->get() as $alarm) {
            if (str_ends_with($alarm->alarm_code, '_high')) {
                $alarmCounts[$alarm->parameter_name] = 3;
            } elseif (str_ends_with($alarm->alarm_code, '_low')) {
                $alarmCounts[$alarm->parameter_name] = -3;
            }
        }

        return [
            'latest' => $latest->getAttributes(),
            'bucket' => null,
            'seen_ids' => [],
            'alarm_counts' => $alarmCounts,
            'usage_day_key' => now()->startOfDay()->toDateString(),
            'usage_month_key' => now()->startOfMonth()->toDateString(),
            'usage_today' => (float) $device->telemetryRaw()->where('recorded_at', '>=', now()->startOfDay())->sum('usage_liter'),
            'usage_month' => (float) $device->telemetryRaw()->where('recorded_at', '>=', now()->startOfMonth())->sum('usage_liter'),
        ];
    }

    private function acknowledgeValve(Device $device, array $payload, TelemetryLatest $latest): void
    {
        if (! isset($payload['command_id'], $payload['valve_state'])) {
            return;
        }

        $command = $device->commands()->whereKey($payload['command_id'])
            ->where('command_key', 'valve_state')->whereIn('status', ['queued', 'sent'])->first();

        if ($command && ! $command->expires_at?->isPast()
            && (int) $latest->valve_state === (int) $command->command_value) {
            $command->update(['status' => 'ack', 'ack_at' => now()]);
        }
    }

    private function storeLate(
        Device $device,
        $setting,
        array $payload,
        Carbon $recordedAt,
        Carbon $receivedAt,
        TelemetryLatest $latest,
        array $state,
        $cache,
        string $key,
    ): string {
        if (TelemetryRaw::where('device_id', $device->id)
            ->where('message_id', $payload['message_id'])->exists()) {
            return 'duplicate';
        }

        $deltaPulse = (int) ($payload['delta_pulse'] ?? 0);
        $pulsePerLiter = (float) $setting->pulse_per_liter;
        $usage = $pulsePerLiter > 0 ? $deltaPulse / $pulsePerLiter : 0.0;
        $values = [];
        foreach (self::METRICS as $metric) {
            if (array_key_exists($metric, $payload)) {
                $values[$metric] = $payload[$metric];
            }
        }
        if (array_key_exists('level_distance_cm', $payload) || array_key_exists('level_cm', $payload)) {
            $level = $this->tankLevel->calculate(
                $payload['level_distance_cm'] ?? $payload['level_cm'], $setting->tank_height_cm,
            );
            $values['level_distance_cm'] = $level['distance_cm'];
            $values['level_cm'] = $level['water_height_cm'];
            $values['level_percent'] = $level['percent'];
        }

        TelemetryRaw::create($values + [
            'device_id' => $device->id,
            'message_id' => $payload['message_id'],
            'delta_pulse' => $deltaPulse,
            'usage_liter' => $usage,
            'pulse_per_liter_used' => $pulsePerLiter,
            'recorded_at' => $recordedAt,
            'raw_payload' => $payload,
        ]);

        $latest->total_pulse = (int) $latest->total_pulse + $deltaPulse;
        $latest->total_usage_liter = (float) $latest->total_usage_liter + $usage;
        $latest->last_seen_at = $receivedAt;
        TelemetryLatest::updateOrCreate(
            ['device_id' => $device->id],
            array_intersect_key($latest->getAttributes(), array_flip($latest->getFillable())),
        );
        $state['latest'] = $latest->getAttributes();
        $state['seen_ids'][] = $payload['message_id'];
        $state['seen_ids'] = array_slice($state['seen_ids'], -2000);
        if ($state['usage_day_key'] === $recordedAt->copy()->startOfDay()->toDateString()) {
            $state['usage_today'] += $usage;
        }
        if ($state['usage_month_key'] === $recordedAt->copy()->startOfMonth()->toDateString()) {
            $state['usage_month'] += $usage;
        }
        $cache->forever($key, $state);

        ReaggregateLateTelemetry::dispatch($recordedAt->toIso8601String());

        return 'accepted';
    }
}
