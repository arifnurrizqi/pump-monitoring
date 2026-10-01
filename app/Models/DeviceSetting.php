<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceSetting extends Model
{
    protected $fillable = [
        'device_id',
        'send_interval_sec',
        'notes',
    ];

    protected $casts = [
        'send_interval_sec' => 'integer',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
