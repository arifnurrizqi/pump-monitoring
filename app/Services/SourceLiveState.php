<?php

namespace App\Services;

use App\Models\Device;
use App\Models\SourceInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class SourceLiveState
{
    public function forDevice(Device $device): array
    {
        $state = $this->applyConnection($device);
        $result = [];

        foreach ($device->sources as $source) {
            $live = $state['sources'][$source->id] ?? null;
            $latest = $live['latest'] ?? [];
            if (! $latest) {
                $interval = SourceInterval::where('data_source_id', $source->id)
                    ->orderByDesc('bucket_start')->first();
                foreach ($interval?->metrics ?? [] as $key => $metric) {
                    $latest[$key] = [
                        'value' => $metric['last'] ?? null,
                        'quality' => $metric['quality'] ?? 'unknown',
                        'recorded_at' => $metric['recorded_at'] ?? $interval->last_recorded_at?->toIso8601String(),
                    ];
                }
            }
            $result[$source->id] = $latest;
        }

        return $result;
    }

    public function applyConnection(Device $device): array
    {
        try {
            $state = Cache::store(config('mqtt.live_cache_store'))->get("telemetry:v2:{$device->id}") ?? [];
        } catch (Throwable $exception) {
            report($exception);
            $state = [];
        }
        $lastSeen = collect($state['sources'] ?? [])->pluck('last_seen_at')->filter()->max();
        if ($lastSeen && (! $device->last_seen_at || Carbon::parse($lastSeen)->greaterThan($device->last_seen_at))) {
            $device->last_seen_at = Carbon::parse($lastSeen);
        }

        return $state;
    }
}
