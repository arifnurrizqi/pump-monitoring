<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlarmLog extends Model
{
    protected $fillable = [
        'device_id',
        'source_parameter_id',
        'alarm_code',
        'parameter_name',
        'alarm_message',
        'current_value',
        'threshold_value',
        'severity',
        'status',
        'started_at',
        'resolved_at',
        'acknowledged_by',
        'acknowledged_at',
    ];

    protected $casts = [
        'current_value' => 'decimal:2',
        'threshold_value' => 'decimal:2',
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
