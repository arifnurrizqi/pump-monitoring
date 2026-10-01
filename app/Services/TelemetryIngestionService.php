<?php

namespace App\Services;

use App\Events\TelemetryUpdated;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\TelemetryLatest;
use App\Models\TelemetryRaw;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class TelemetryIngestionService
{
    public function __construct(
        private readonly AlarmService $alarmService,
        private readonly ParameterHealthService $healthService,
        private readonly TankLevelService $tankLevelService,
        private readonly TelemetryRealtimeService $realtimeService,
    ) {}

    /** @param array<string, mixed> $payload */
    public function ingest(array $payload): JsonResponse
    {
        $validator = Validator::make($payload, [
            'device_sn' => ['required', 'string', 'max:100'],
            'message_id' => ['nullable', 'string', 'max:100'],

            'ph' => ['nullable', 'numeric'],
            'turbidity' => ['nullable', 'numeric'],
            'delta_pulse' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'sample_flow' => ['nullable', 'numeric', 'min:0'],
            'flow' => ['prohibited'],
            'flow_total' => ['prohibited'],
            'level_distance_cm' => ['nullable', 'numeric', 'min:0'],
            'level_cm' => ['nullable', 'numeric', 'min:0'],
            'device_voltage_mcu' => ['nullable', 'numeric'],
            'device_voltage_5v' => ['nullable', 'numeric'],
            'device_voltage_input' => ['nullable', 'numeric'],
            'signal_strength' => ['nullable', 'integer'],
            'status_code' => ['nullable', 'string', 'max:50'],
            'alarm_code' => ['nullable', 'string', 'max:50'],
            'valve_state' => ['nullable', 'boolean', 'max:50'],
            'command_id' => ['nullable', 'integer', 'min:1'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $validator->after(function ($validator) use ($payload): void {
            if (array_key_exists('level_distance_cm', $payload) && array_key_exists('level_cm', $payload)) {
                $message = 'Kirim salah satu antara level_distance_cm atau level_cm, bukan keduanya.';
                $validator->errors()->add('level_distance_cm', $message);
                $validator->errors()->add('level_cm', $message);
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Payload tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verifikasi device berdasarkan device_sn
        $device = Device::where('device_sn', $payload['device_sn'])->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device tidak terdaftar',
            ], 404);
        }

        if (! empty($payload['message_id'])
            && TelemetryRaw::where('device_id', $device->id)
                ->where('message_id', $payload['message_id'])->exists()) {
            return response()->json(['success' => true, 'duplicate' => true]);
        }

        // Kalau nanti ada field status aktif/nonaktif, bisa ditambahkan di sini
        // contoh:
        // if (!$device->is_active) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Device nonaktif',
        //     ], 403);
        // }

        $recordedAt = ! empty($payload['recorded_at'])
          ? Carbon::parse($payload['recorded_at'])
          : now();

        $setting = $device->setting()->firstOrCreate([], [
            'pulse_per_liter' => 450,
            'alarm_enabled' => true,
            'sampling_interval_sec' => 10,
            'send_interval_sec' => 10,
            'timezone' => 'Asia/Jakarta',
        ]);
        $levelWasSubmitted = array_key_exists('level_distance_cm', $payload)
            || array_key_exists('level_cm', $payload);
        $levelDistanceCm = $payload['level_distance_cm'] ?? $payload['level_cm'] ?? null;
        $level = $this->tankLevelService->calculate($levelDistanceCm, $setting->tank_height_cm);

        $deltaPulse = $payload['delta_pulse'] ?? 0;
        $pulsePerLiter = (float) $setting->pulse_per_liter;
        $usageLiter = $pulsePerLiter > 0 ? $deltaPulse / $pulsePerLiter : 0.0;
        $receivedAt = now();

        $telemetry = DB::transaction(function () use (
            $device,
            $payload,
            $recordedAt,
            $receivedAt,
            $level,
            $levelWasSubmitted,
            $deltaPulse,
            $pulsePerLiter,
            $usageLiter,
        ) {
            $telemetry = TelemetryRaw::create([
                'device_id' => $device->id,
                'message_id' => $payload['message_id'] ?? null,
                'ph' => $payload['ph'] ?? null,
                'turbidity' => $payload['turbidity'] ?? null,
                'delta_pulse' => $deltaPulse,
                'pulse_per_liter_used' => $pulsePerLiter,
                'usage_liter' => $usageLiter,
                'sample_flow' => $payload['sample_flow'] ?? null,
                'level_distance_cm' => $level['distance_cm'],
                'level_cm' => $level['water_height_cm'],
                'level_percent' => $level['percent'],
                'device_voltage_mcu' => $payload['device_voltage_mcu'] ?? null,
                'device_voltage_5v' => $payload['device_voltage_5v'] ?? null,
                'device_voltage_input' => $payload['device_voltage_input'] ?? null,
                'signal_strength' => $payload['signal_strength'] ?? null,
                'status_code' => $payload['status_code'] ?? null,
                'alarm_code' => $payload['alarm_code'] ?? null,
                'valve_state' => $payload['valve_state'] ?? null,
                'raw_payload' => $payload,
                'recorded_at' => $recordedAt,
            ]);

            $latest = TelemetryLatest::where('device_id', $device->id)
                ->lockForUpdate()
                ->first();

            if (! $latest) {
                $latest = new TelemetryLatest([
                    'device_id' => $device->id,
                    'total_pulse' => 0,
                    'total_usage_liter' => 0,
                ]);
            }

            $latestValues = [
                'total_pulse' => (int) $latest->total_pulse + $deltaPulse,
                'last_delta_pulse' => $deltaPulse,
                'last_usage_liter' => $usageLiter,
                'total_usage_liter' => (float) $latest->total_usage_liter + $usageLiter,
                'recorded_at' => $recordedAt,
                'last_seen_at' => $receivedAt,
            ];

            foreach ([
                'ph',
                'turbidity',
                'sample_flow',
                'device_voltage_mcu',
                'device_voltage_5v',
                'device_voltage_input',
                'signal_strength',
                'alarm_code',
                'valve_state',
            ] as $field) {
                if (array_key_exists($field, $payload)) {
                    $latestValues[$field] = $payload[$field];
                }
            }

            if ($levelWasSubmitted) {
                $latestValues['level_distance_cm'] = $level['distance_cm'];
                $latestValues['level_cm'] = $level['water_height_cm'];
                $latestValues['level_percent'] = $level['percent'];
            }

            $latest->fill($latestValues);
            $health = $this->healthService->evaluate($latest);
            $qualityStatus = $this->healthService->waterQuality($health);
            $latest->status_code = $qualityStatus;
            $latest->save();

            $telemetry->update(['status_code' => $qualityStatus]);

            $device->update(['last_seen_at' => $receivedAt]);
            $this->alarmService->evaluate($telemetry);
            $this->acknowledgeValveCommand($device, $telemetry, $payload['command_id'] ?? null);

            return $telemetry;
        }, 3);

        $latest = $device->latestData()->firstOrFail();
        $currentHealth = $this->healthService->evaluate($latest);
        $usageMonthLiter = $this->usageThisMonth($device, $setting->timezone ?: config('app.timezone'));

        try {
            TelemetryUpdated::dispatch(
                $device->id,
                $this->realtimeService->snapshot($device, $telemetry, $latest, $currentHealth),
            );
        } catch (Throwable $exception) {
            report($exception);
        }

        return response()->json([
            'success' => true,
            'message' => 'Telemetry berhasil disimpan',
            'data' => [
                'id' => $telemetry->id,
                'device_id' => $telemetry->device_id,
                'device_sn' => $device->device_sn,
                'recorded_at' => $telemetry->recorded_at,
                'usage_liter' => (float) $telemetry->usage_liter,
                'usage_month_liter' => $usageMonthLiter,
                'level_distance_cm' => $telemetry->level_distance_cm === null
                    ? null
                    : (float) $telemetry->level_distance_cm,
                'water_height_cm' => $telemetry->level_cm === null ? null : (float) $telemetry->level_cm,
                'level_percent' => $telemetry->level_percent === null ? null : (float) $telemetry->level_percent,
                'quality_status' => $telemetry->status_code,
                'parameter_health' => $currentHealth,
            ],
        ], 201);
    }

    private function usageThisMonth(Device $device, string $timezone): float
    {
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            $timezone = config('app.timezone');
        }

        $now = now($timezone);
        $usage = $device->telemetryRaw()
            ->whereBetween('recorded_at', [
                $now->copy()->startOfMonth(),
                $now->copy(),
            ])
            ->sum('usage_liter');

        return round((float) $usage, 6);
    }

    private function acknowledgeValveCommand(
        Device $device,
        TelemetryRaw $telemetry,
        ?int $commandId
    ): void {
        if ($telemetry->valve_state === null) {
            return;
        }

        if (! $commandId) {
            return;
        }

        $query = DeviceCommand::where('device_id', $device->id)
            ->whereIn('status', ['queued', 'sent'])
            ->where('command_key', 'valve_state');

        $command = $query->whereKey($commandId)->first();

        if ($command && ! $command->expires_at?->isPast()
            && (int) $telemetry->valve_state === (int) $command->command_value) {
            $command->update([
                'status' => 'ack',
                'ack_at' => now(),
            ]);
        }
    }
}
