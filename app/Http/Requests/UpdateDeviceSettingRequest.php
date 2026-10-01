<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeviceSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'tank_height_cm' => ['nullable', 'numeric', 'gt:0'],
            'tank_volume_liter' => ['nullable', 'numeric', 'min:0'],

            'ph_min' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'ph_max' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'pulse_per_liter' => ['required', 'numeric', 'gt:0'],

            'turbidity_max' => ['nullable', 'numeric', 'min:0'],

            'sample_flow_min' => ['nullable', 'numeric', 'min:0'],
            'sample_flow_max' => ['nullable', 'numeric', 'min:0'],

            'level_min_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'level_max_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'device_voltage_mcu_min' => ['nullable', 'numeric', 'min:0'],
            'device_voltage_5v_min' => ['nullable', 'numeric', 'min:0'],
            'device_voltage_input_min' => ['nullable', 'numeric', 'min:0'],

            'sampling_interval_sec' => ['nullable', 'integer', 'min:1'],
            'send_interval_sec' => ['nullable', 'integer', 'min:1'],

            'alarm_enabled' => ['nullable', 'boolean'],
            'timezone' => ['nullable', 'timezone'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $phMin = $this->input('ph_min');
            $phMax = $this->input('ph_max');

            if ($phMin !== null && $phMax !== null && $phMin > $phMax) {
                $validator->errors()->add('ph_min', 'pH minimum tidak boleh lebih besar dari pH maximum.');
            }

            $flowMin = $this->input('sample_flow_min');
            $flowMax = $this->input('sample_flow_max');

            if ($flowMin !== null && $flowMax !== null && $flowMin > $flowMax) {
                $validator->errors()->add('sample_flow_min', 'Sample flow minimum tidak boleh lebih besar dari sample flow maximum.');
            }

            $levelMin = $this->input('level_min_percent');
            $levelMax = $this->input('level_max_percent');

            if ($levelMin !== null && $levelMax !== null && $levelMin > $levelMax) {
                $validator->errors()->add('level_min_percent', 'Level minimum tidak boleh lebih besar dari level maximum.');
            }
        });
    }
}
