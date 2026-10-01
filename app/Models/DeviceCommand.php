<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceCommand extends Model
{
    protected $table = 'device_commands';

    protected $fillable = [
        'device_id',
        'command_type',
        'command_key',
        'command_value',
        'sent_at',
        'status',
        'attempts',
        'ack_at',
        'expires_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'ack_at' => 'datetime',
        'expires_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Relasi ke Device
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
