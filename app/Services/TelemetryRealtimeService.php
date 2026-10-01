<?php

namespace App\Services;

use App\Models\Device;
use App\Models\TelemetryLatest;
use App\Models\TelemetryRaw;

class TelemetryRealtimeService
{
    public function snapshot(
        Device $device,
        TelemetryRaw $telemetry,
        TelemetryLatest $latest,
        array $health,
        ?float $usageToday = null,
        ?float $usageMonth = null,
    ): array {
        $device->loadMissing('setting');
        $device->setRelation('latestData', $latest);
        $usageBucket = $telemetry->recorded_at?->copy()->startOfMinute();
        $usageBucket?->subMinutes($usageBucket->minute % 5);

        return [
            'device' => [
                'id' => $device->id,
                'device_sn' => $device->device_sn,
                'connection_status' => $device->connection_status,
                'send_interval_sec' => (int) ($device->setting?->send_interval_sec ?? 10),
                'active_alarm_count' => $device->activeAlarms()->count(),
            ],
            'telemetry' => [
                'ph' => $this->floatOrNull($latest->ph),
                'turbidity' => $this->floatOrNull($latest->turbidity),
                'sample_flow' => $this->floatOrNull($latest->sample_flow),
                'delta_pulse' => (int) $telemetry->delta_pulse,
                'total_pulse' => (int) $latest->total_pulse,
                'usage_liter' => (float) $telemetry->usage_liter,
                'total_usage_liter' => (float) $latest->total_usage_liter,
                'usage_today_liter' => $usageToday ?? $this->usageBetween($device, now()->startOfDay(), now()),
                'usage_month_liter' => $usageMonth ?? $this->usageBetween($device, now()->startOfMonth(), now()),
                'level_distance_cm' => $this->floatOrNull($latest->level_distance_cm),
                'level_percent' => $this->floatOrNull($latest->level_percent),
                'level_cm' => $this->floatOrNull($latest->level_cm),
                'device_voltage_mcu' => $this->floatOrNull($latest->device_voltage_mcu),
                'device_voltage_5v' => $this->floatOrNull($latest->device_voltage_5v),
                'device_voltage_input' => $this->floatOrNull($latest->device_voltage_input),
                'signal_strength' => $latest->signal_strength,
                'valve_state' => $latest->valve_state,
                'alarm_code' => $latest->alarm_code,
                'quality_status' => $latest->status_code,
                'parameter_health' => $health,
                'recorded_at' => $latest->recorded_at?->toIso8601String(),
                'recorded_at_display' => $latest->recorded_at?->format('d M Y, H:i:s'),
                'last_seen_at' => $latest->last_seen_at?->toIso8601String(),
                'last_seen_at_display' => $latest->last_seen_at?->format('d M Y, H:i:s'),
            ],
            'chart_point' => [
                'label' => $telemetry->recorded_at?->format('H:i:s'),
                'usage_label' => $usageBucket?->format('H:i'),
                'ph' => $this->floatOrNull($latest->ph),
                'turbidity' => $this->floatOrNull($latest->turbidity),
                'level' => $this->floatOrNull($latest->level_percent),
                'usage' => (float) $telemetry->usage_liter,
            ],
        ];
    }

    private function usageBetween(Device $device, $from, $to): float
    {
        return (float) $device->telemetryRaw()
            ->whereBetween('recorded_at', [$from, $to])
            ->sum('usage_liter');
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
