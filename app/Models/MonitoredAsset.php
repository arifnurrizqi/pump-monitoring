<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonitoredAsset extends Model
{
    protected $fillable = ['production_site_id', 'name', 'type', 'phase_count'];

    protected $casts = ['phase_count' => 'integer'];

    public function site(): BelongsTo
    {
        return $this->belongsTo(ProductionSite::class, 'production_site_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }
}
