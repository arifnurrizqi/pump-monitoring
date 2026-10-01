<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceParameter extends Model
{
    protected $fillable = [
        'data_source_id', 'key', 'label', 'unit', 'alarm_min',
        'alarm_max', 'alarm_enabled', 'sort_order',
    ];

    protected $casts = [
        'alarm_min' => 'decimal:6',
        'alarm_max' => 'decimal:6',
        'alarm_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }
}
