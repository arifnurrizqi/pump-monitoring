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
        'api_token',
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

    public function telemetryRaw(): HasMany
    {
        return $this->hasMany(TelemetryRaw::class);
    }

    public function latestCommand()
    {
        return $this->hasOne(DeviceCommand::class)
            ->latestOfMany();
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function latestTelemetry(): HasOne
    {
        return $this->hasOne(TelemetryRaw::class)->latestOfMany('recorded_at');
    }

    public function alarmLogs(): HasMany
    {
        return $this->hasMany(AlarmLog::class);
    }

    public function activeAlarms(): HasMany
    {
        return $this->hasMany(AlarmLog::class)->where('status', 'active');
    }

    public function telemetryHourly(): HasMany
    {
        return $this->hasMany(TelemetryHourly::class);
    }

    public function telemetryDaily(): HasMany
    {
        return $this->hasMany(TelemetryDaily::class);
    }

    public function telemetryMonthly(): HasMany
    {
        return $this->hasMany(TelemetryMonthly::class);
    }

    public function telemetryYearly(): HasMany
    {
        return $this->hasMany(TelemetryYearly::class);
    }

    public function latestData()
    {
        return $this->hasOne(TelemetryLatest::class);
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

    public function getPhAttribute()
    {
        return $this->latestData?->ph;
    }

    public function getTurbidityAttribute()
    {
        return $this->latestData?->turbidity;
    }

    public function getLevelAttribute()
    {
        return $this->latestData?->level_percent !== null ? (int) $this->latestData->level_percent : null;
    }

    public function getAlarmCountAttribute(): int
    {
        return $this->activeAlarms->count();
    }

    public function getStatusAttribute(): string
    {
        return $this->connection_status;
    }

    public function getLastUpdateAttribute()
    {
        $legacy = $this->latestData?->recorded_at;

        return $this->last_seen_at && (! $legacy || $this->last_seen_at->greaterThan($legacy))
            ? $this->last_seen_at : $legacy;
    }

    public function getUsageTodayAttribute(): float
    {
        $state = $this->relationLoaded('liveTelemetryState')
            ? $this->getRelation('liveTelemetryState') : null;
        if ($state && ($state['usage_day_key'] ?? null) === now()->startOfDay()->toDateString()) {
            return round((float) $state['usage_today'], 2);
        }

        $usageLiter = (float) $this->telemetryRaw()
            ->where('recorded_at', '>=', now()->startOfDay())
            ->sum('usage_liter');

        return round($usageLiter, 2);
    }

    public function getUsageMonthAttribute(): float
    {
        $state = $this->relationLoaded('liveTelemetryState')
            ? $this->getRelation('liveTelemetryState') : null;
        if ($state && ($state['usage_month_key'] ?? null) === now()->startOfMonth()->toDateString()) {
            return round((float) $state['usage_month'] / 1000, 2);
        }

        $usageLiter = (float) $this->telemetryRaw()
            ->where('recorded_at', '>=', now()->startOfMonth())
            ->sum('usage_liter');

        return round($usageLiter / 1000, 2);
    }

    public function getLastTelemetryAgeSecondsAttribute(): ?int
    {
        $recordedAt = $this->latestData?->recorded_at;

        if (! $recordedAt) {
            return null;
        }

        return now()->diffInSeconds($recordedAt);
    }
}
