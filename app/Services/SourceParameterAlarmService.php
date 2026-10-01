<?php

namespace App\Services;

use App\Models\AlarmLog;
use App\Models\Device;
use App\Models\SourceParameter;

class SourceParameterAlarmService
{
    /** @param array<string, int> $counts */
    public function evaluate(Device $device, SourceParameter $parameter, ?float $value, string $quality, array &$counts): void
    {
        if (! $parameter->alarm_enabled || $quality !== 'good' || $value === null) {
            return;
        }

        $min = $parameter->alarm_min;
        $max = $parameter->alarm_max;
        $previous = $counts[$parameter->id] ?? 0;
        $next = $min !== null && $value < (float) $min
            ? max(-3, $previous < 0 ? $previous - 1 : -1)
            : ($max !== null && $value > (float) $max
                ? min(3, $previous > 0 ? $previous + 1 : 1)
                : 0);
        $counts[$parameter->id] = $next;

        if ($previous !== 0 && ($next === 0 || ($previous > 0) !== ($next > 0))) {
            AlarmLog::where('source_parameter_id', $parameter->id)
                ->whereIn('status', ['active', 'acknowledged'])
                ->update(['status' => 'resolved', 'resolved_at' => now()]);
        }

        if ($next === 3 || $next === -3) {
            $side = $next > 0 ? 'high' : 'low';
            $existing = AlarmLog::where('source_parameter_id', $parameter->id)
                ->where('alarm_code', "param_{$parameter->id}_{$side}")
                ->whereIn('status', ['active', 'acknowledged'])->first();
            if (! $existing) {
                AlarmLog::create([
                    'source_parameter_id' => $parameter->id,
                    'alarm_code' => "param_{$parameter->id}_{$side}",
                    'status' => 'active',
                    'device_id' => $device->id,
                    'parameter_name' => $parameter->key,
                    'alarm_message' => "{$parameter->source->name}: {$parameter->label} {$side}",
                    'current_value' => $value,
                    'threshold_value' => $next > 0 ? $max : $min,
                    'severity' => 'warning',
                    'started_at' => now(),
                ]);
            }
        }
    }
}
