<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryLatest extends Model
{
    protected $table = 'telemetry_latest';

    protected $fillable = [
        'device_id',

        'ph',
        'turbidity',
        'total_pulse',
        'last_delta_pulse',
        'last_usage_liter',
        'total_usage_liter',
        'sample_flow',

        'level_distance_cm',
        'level_percent',
        'level_cm',

        'device_voltage_mcu',
        'device_voltage_5v',
        'device_voltage_input',

        'signal_strength',
        'status_code',
        'alarm_code',
        'valve_state',

        'recorded_at',
        'last_seen_at',
    ];

    protected $casts = [
        'ph' => 'decimal:2',
        'turbidity' => 'decimal:2',
        'total_pulse' => 'integer',
        'last_delta_pulse' => 'integer',
        'last_usage_liter' => 'decimal:6',
        'total_usage_liter' => 'decimal:6',
        'sample_flow' => 'decimal:2',

        'level_distance_cm' => 'decimal:2',
        'level_percent' => 'decimal:2',
        'level_cm' => 'decimal:2',

        'device_voltage_mcu' => 'decimal:2',
        'device_voltage_5v' => 'decimal:2',
        'device_voltage_input' => 'decimal:2',
        'valve_state' => 'boolean',

        'signal_strength' => 'integer',
        'recorded_at' => 'datetime',
        'last_seen_at' => 'datetime',

    ];

    /**
     * Relasi ke device
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
