<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryRaw extends Model
{
    protected $table = 'telemetry_raw';

    protected $fillable = [
        'device_id',
        'message_id',
        'bucket_start',
        'sample_count',
        'ph',
        'turbidity',
        'delta_pulse',
        'pulse_per_liter_used',
        'usage_liter',
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
        'raw_payload',
        'recorded_at',
    ];

    protected $casts = [
        'ph' => 'decimal:2',
        'turbidity' => 'decimal:2',
        'delta_pulse' => 'integer',
        'pulse_per_liter_used' => 'decimal:4',
        'usage_liter' => 'decimal:6',
        'sample_flow' => 'decimal:2',
        'level_distance_cm' => 'decimal:2',
        'level_percent' => 'decimal:2',
        'level_cm' => 'decimal:2',
        'device_voltage_mcu' => 'decimal:2',
        'device_voltage_5v' => 'decimal:2',
        'device_voltage_input' => 'decimal:2',
        'signal_strength' => 'integer',
        'valve_state' => 'boolean',
        'raw_payload' => 'array',
        'recorded_at' => 'datetime',
        'bucket_start' => 'datetime',
        'sample_count' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
