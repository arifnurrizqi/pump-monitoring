<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSetting extends Model
{
    protected $fillable = [
        'device_id',
        'tank_height_cm',
        'tank_volume_liter',
        'ph_min',
        'ph_max',
        'pulse_per_liter',
        'turbidity_max',
        'sample_flow_min',
        'sample_flow_max',
        'level_min_percent',
        'level_max_percent',
        'device_voltage_mcu_min',
        'device_voltage_5v_min',
        'device_voltage_input_min',
        'sampling_interval_sec',
        'send_interval_sec',
        'alarm_enabled',
        'timezone',
        'notes',
    ];

    protected $casts = [
        'tank_height_cm' => 'decimal:2',
        'tank_volume_liter' => 'decimal:2',
        'ph_min' => 'decimal:2',
        'ph_max' => 'decimal:2',
        'pulse_per_liter' => 'decimal:2',
        'turbidity_max' => 'decimal:2',
        'sample_flow_min' => 'decimal:2',
        'sample_flow_max' => 'decimal:2',
        'level_min_percent' => 'decimal:2',
        'level_max_percent' => 'decimal:2',
        'device_voltage_mcu_min' => 'decimal:2',
        'device_voltage_5v_min' => 'decimal:2',
        'device_voltage_input_min' => 'decimal:2',
        'sampling_interval_sec' => 'integer',
        'send_interval_sec' => 'integer',
        'alarm_enabled' => 'boolean',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
