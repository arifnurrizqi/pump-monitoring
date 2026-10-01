<?php

namespace App\Services;

use App\Models\AlarmLog;
use App\Models\DeviceSetting;
use App\Models\TelemetryRaw;

class AlarmService
{
    /** @param array<string, int> $counts */
    public function evaluateLive(TelemetryRaw $telemetry, DeviceSetting $setting, array &$counts): void
    {
        if (! $setting->alarm_enabled) {
            return;
        }

        foreach ([
            'ph' => [$setting->ph_min, $setting->ph_max],
            'turbidity' => [0, $setting->turbidity_max],
            'sample_flow' => [$setting->sample_flow_min, $setting->sample_flow_max],
            'level_percent' => [$setting->level_min_percent, $setting->level_max_percent],
            'device_voltage_mcu' => [$setting->device_voltage_mcu_min, 3.5],
            'device_voltage_5v' => [$setting->device_voltage_5v_min, 5.3],
            'device_voltage_input' => [$setting->device_voltage_input_min, 36],
        ] as $parameter => [$min, $max]) {
            $value = $telemetry->{$parameter};

            if ($value === null || $min === null || $max === null) {
                continue;
            }

            $previous = $counts[$parameter] ?? 0;
            $next = (float) $value < (float) $min
                ? max(-3, $previous < 0 ? $previous - 1 : -1)
                : ((float) $value > (float) $max
                    ? min(3, $previous > 0 ? $previous + 1 : 1)
                    : 0);
            if (($previous === 3 && $next < 0) || ($previous === -3 && $next > 0)) {
                $this->resolveAlarm($telemetry, $parameter);
            }
            $counts[$parameter] = $next;

            if ($next === -3 && $previous !== -3) {
                $this->triggerAlarm($telemetry, "{$parameter}_low", $parameter, $value, $min);
            } elseif ($next === 3 && $previous !== 3) {
                $this->triggerAlarm($telemetry, "{$parameter}_high", $parameter, $value, $max);
            } elseif ($next === 0 && $previous !== 0) {
                $this->resolveAlarm($telemetry, $parameter);
            }
        }
    }

    public function evaluate(TelemetryRaw $telemetry)
    {
        $setting = DeviceSetting::where('device_id', $telemetry->device_id)->first();

        if (! $setting || ! $setting->alarm_enabled) {
            return;
        }

        // PH
        $this->checkRange(
            $telemetry,
            $setting,
            'ph',
            $telemetry->ph,
            $setting->ph_min,
            $setting->ph_max
        );

        // TURBIDITY
        $this->checkRange(
            $telemetry,
            $setting,
            'turbidity',
            $telemetry->turbidity,
            0,
            $setting->turbidity_max
        );

        // SAMPLE FLOW
        $this->checkRange(
            $telemetry,
            $setting,
            'sample_flow',
            $telemetry->sample_flow,
            $setting->sample_flow_min,
            $setting->sample_flow_max
        );

        // LEVEL
        $this->checkRange(
            $telemetry,
            $setting,
            'level_percent',
            $telemetry->level_percent,
            $setting->level_min_percent,
            $setting->level_max_percent
        );

        // VOLTAGE MCU
        $this->checkRange(
            $telemetry,
            $setting,
            'device_voltage_mcu',
            $telemetry->device_voltage_mcu,
            $setting->device_voltage_mcu_min,
            3.5
        );

        // VOLTAGE 5V
        $this->checkRange(
            $telemetry,
            $setting,
            'device_voltage_5v',
            $telemetry->device_voltage_5v,
            $setting->device_voltage_5v_min,
            5.3
        );

        // VOLTAGE VCC
        $this->checkRange(
            $telemetry,
            $setting,
            'device_voltage_input',
            $telemetry->device_voltage_input,
            $setting->device_voltage_input_min,
            36
        );
    }

    /**
     * CORE LOGIC: 3x anomaly rule + 1x normal resolve
     */
    private function checkRange(
        TelemetryRaw $telemetry,
        DeviceSetting $setting,
        string $parameter,
        $value,
        ?float $min,
        ?float $max
    ) {
        if (is_null($value) || is_null($min) || is_null($max)) {
            return;
        }

        $value = (float) $value;

        $lastThree = TelemetryRaw::where('device_id', $telemetry->device_id)
            ->whereNotNull($parameter)
            ->latest('recorded_at')
            ->limit(3)
            ->get();

        if ($lastThree->count() < 3) {
            return;
        }

        $lowCount = $lastThree->where($parameter, '<', $min)->count();
        $highCount = $lastThree->where($parameter, '>', $max)->count();

        // 3x LOW anomaly
        if ($lowCount === 3) {

            $this->triggerAlarm(
                $telemetry,
                "{$parameter}_low",
                $parameter,
                $value,
                $min
            );

            return;
        }

        // 3x HIGH anomaly
        if ($highCount === 3) {

            $this->triggerAlarm(
                $telemetry,
                "{$parameter}_high",
                $parameter,
                $value,
                $max
            );

            return;
        }

        // NORMAL → resolve semua alarm parameter ini
        $this->resolveAlarm($telemetry, $parameter);
    }

    /**
     * CREATE ALARM (avoid duplicate active/already acknowledged)
     */
    private function triggerAlarm($telemetry, $code, $param, $value, $threshold)
    {
        $active = AlarmLog::where('device_id', $telemetry->device_id)
            ->where('alarm_code', $code)
            ->whereIn('status', ['active', 'acknowledged'])
            ->first();

        if ($active) {
            return;
        }

        AlarmLog::create([
            'device_id' => $telemetry->device_id,
            'alarm_code' => $code,
            'parameter_name' => $param,
            'alarm_message' => strtoupper($code),
            'current_value' => $value,
            'threshold_value' => $threshold,
            'severity' => 'warning',
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    /**
     * RESOLVE ALARM (1 data normal cukup)
     */
    private function resolveAlarm($telemetry, $parameter)
    {
        AlarmLog::where('device_id', $telemetry->device_id)
            ->where('parameter_name', $parameter)
            ->whereIn('status', ['active', 'acknowledged'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }
}
