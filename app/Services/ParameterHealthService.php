<?php

namespace App\Services;

use App\Models\DeviceSetting;
use Illuminate\Database\Eloquent\Model;

class ParameterHealthService
{
    public function evaluate(Model $telemetry): array
    {
        $setting = DeviceSetting::where('device_id', $telemetry->device_id)->first();

        if (! $setting) {
            return [];
        }

        $result = [];

        // PH
        $result['ph'] = $this->evaluatePh($telemetry->ph, $setting);

        // TURBIDITY
        $result['turbidity'] = $this->evaluateTurbidity($telemetry->turbidity, $setting);

        // LEVEL
        $result['level_percent'] = $this->evaluateLevel($telemetry->level_percent, $setting);

        // SAMPLE FLOW
        $result['sample_flow'] = $this->evaluateSampleFlow($telemetry->sample_flow, $setting);

        return $result;
    }

    /* =========================
     * PH
     * ========================= */
    private function evaluatePh($value, $setting): string
    {
        if (is_null($value)) {
            return 'unknown';
        }
        if ($value >= $setting->ph_min && $value <= $setting->ph_max) {
            return 'good';
        }
        if (
            ($value >= ($setting->ph_min - 0.5) && $value < $setting->ph_min) ||
            ($value > $setting->ph_max && $value <= $setting->ph_max + 0.5)
        ) {
            return 'warning';
        }

        return 'bad';
    }

    /* =========================
     * TURBIDITY
     * ========================= */
    private function evaluateTurbidity($value, $setting): string
    {
        if (is_null($value)) {
            return 'unknown';
        }

        if ($value <= $setting->turbidity_max) {
            return 'good';
        }

        if ($value <= 50) {
            return 'warning';
        }

        return 'bad';
    }

    /* =========================
     * LEVEL
     * ========================= */
    private function evaluateLevel($value, $setting): string
    {
        if (is_null($value)) {
            return 'unknown';
        }

        if ($setting->level_min_percent === null || $setting->level_max_percent === null) {
            return 'unknown';
        }

        if ($value >= $setting->level_max_percent) {
            return 'good';
        }
        if ($value >= $setting->level_min_percent) {
            return 'warning';
        }

        return 'bad';
    }

    private function evaluateSampleFlow($value, $setting): string
    {
        if (
            is_null($value) ||
            $setting->sample_flow_min === null ||
            $setting->sample_flow_max === null
        ) {
            return 'unknown';
        }

        if ($value >= $setting->sample_flow_min && $value <= $setting->sample_flow_max) {
            return 'good';
        }

        return 'bad';
    }

    public function overall(array $health): string
    {
        if (empty($health)) {
            return 'unknown';
        }

        if (in_array('bad', $health, true)) {
            return 'bad';
        }

        if (in_array('warning', $health, true)) {
            return 'warning';
        }

        if (in_array('good', $health, true)) {
            return 'good';
        }

        return 'unknown';
    }

    public function waterQuality(array $health): string
    {
        $quality = array_intersect_key($health, array_flip([
            'ph',
            'turbidity',
        ]));

        if (in_array('bad', $quality, true)) {
            return 'bad';
        }

        if (count($quality) < 2 || in_array('unknown', $quality, true)) {
            return 'unknown';
        }

        if (count(array_filter($quality, fn (string $status) => $status === 'warning')) >= 2) {
            return 'bad';
        }

        return $this->overall($quality);
    }
}
