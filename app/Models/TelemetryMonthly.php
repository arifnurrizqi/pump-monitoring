<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryMonthly extends Model
{
    protected $table = 'telemetry_monthly';

    protected $fillable = [
        'device_id',
        'period_start',
        'period_end',
        'avg_ph',
        'min_ph',
        'max_ph',
        'avg_turbidity',
        'min_turbidity',
        'max_turbidity',
        'avg_sample_flow',
        'min_sample_flow',
        'max_sample_flow',
        'usage_liter',
        'avg_level_percent',
        'min_level_percent',
        'max_level_percent',
        'avg_level_cm',
        'min_level_cm',
        'max_level_cm',
        'avg_device_voltage_mcu',
        'min_device_voltage_mcu',
        'max_device_voltage_mcu',
        'avg_device_voltage_5v',
        'min_device_voltage_5v',
        'max_device_voltage_5v',
        'avg_device_voltage_input',
        'min_device_voltage_input',
        'max_device_voltage_input',
        'avg_signal_strength',
        'min_signal_strength',
        'max_signal_strength',
        'sample_count',
    ];

    protected $casts = [
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'avg_ph' => 'decimal:2',
        'min_ph' => 'decimal:2',
        'max_ph' => 'decimal:2',
        'avg_turbidity' => 'decimal:2',
        'min_turbidity' => 'decimal:2',
        'max_turbidity' => 'decimal:2',
        'avg_sample_flow' => 'decimal:2',
        'min_sample_flow' => 'decimal:2',
        'max_sample_flow' => 'decimal:2',
        'usage_liter' => 'decimal:6',
        'avg_level_percent' => 'decimal:2',
        'min_level_percent' => 'decimal:2',
        'max_level_percent' => 'decimal:2',
        'avg_level_cm' => 'decimal:2',
        'min_level_cm' => 'decimal:2',
        'max_level_cm' => 'decimal:2',
        'avg_device_voltage_mcu' => 'decimal:2',
        'min_device_voltage_mcu' => 'decimal:2',
        'max_device_voltage_mcu' => 'decimal:2',
        'avg_device_voltage_5v' => 'decimal:2',
        'min_device_voltage_5v' => 'decimal:2',
        'max_device_voltage_5v' => 'decimal:2',
        'avg_device_voltage_input' => 'decimal:2',
        'min_device_voltage_input' => 'decimal:2',
        'max_device_voltage_input' => 'decimal:2',
        'avg_signal_strength' => 'decimal:2',
        'min_signal_strength' => 'integer',
        'max_signal_strength' => 'integer',
        'sample_count' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
