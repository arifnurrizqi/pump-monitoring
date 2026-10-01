<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Device extends Model
{
    protected $fillable = [
        'device_sn',
        'device_tag',
        'location',
        'description',
        'is_active',
        'last_seen_at',
        'installed_at',
        'production_site_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
        'installed_at' => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'device_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(ProductionSite::class, 'production_site_id');
    }

    public function sources(): HasMany
    {
        return $this->hasMany(DataSource::class);
    }

    public function setting(): HasOne
    {
        return $this->hasOne(DeviceSetting::class);
    }

    public function alarmLogs(): HasMany
    {
        return $this->hasMany(AlarmLog::class);
    }

    public function activeAlarms(): HasMany
    {
        return $this->hasMany(AlarmLog::class)->where('status', 'active');
    }

    public function getConnectionStatusAttribute(): string
    {
        $last_seen_at = $this->last_seen_at;

        if (! $last_seen_at) {
            return 'offline';
        }

        $sendInterval = $this->setting?->send_interval_sec ?? 10;
        $diff = now()->diffInSeconds($last_seen_at, true);

        if ($diff <= ($sendInterval * 9)) {
            return 'online';
        }

        if ($diff <= ($sendInterval * 18)) {
            return 'pending';
        }

        return 'offline';
    }

}
