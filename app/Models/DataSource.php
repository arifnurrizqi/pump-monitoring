<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = ['device_id', 'monitored_asset_id', 'source_key', 'name', 'type', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MonitoredAsset::class, 'monitored_asset_id');
    }

    public function parameters(): HasMany
    {
        return $this->hasMany(SourceParameter::class);
    }

    public function intervals(): HasMany
    {
        return $this->hasMany(SourceInterval::class);
    }
}
