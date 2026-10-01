<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionSite extends Model
{
    protected $fillable = ['code', 'name', 'location'];

    public function assets(): HasMany
    {
        return $this->hasMany(MonitoredAsset::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
