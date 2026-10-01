<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeviceChartService
{
    public const RANGES = [
        '1h' => ['seconds' => 3600, 'bucket' => 60],
        '12h' => ['seconds' => 43200, 'bucket' => 300],
        '1d' => ['seconds' => 86400, 'bucket' => 600],
        '7d' => ['seconds' => 604800, 'bucket' => 3600],
        '1mo' => ['seconds' => 2592000, 'bucket' => 21600],
    ];

    /** @return array<string, array{key: string, title: string, series: array<int, array<string, mixed>>}> */
    public function groups(Device $device): array
    {
        $groups = [];
        foreach ($device->sources as $source) {
            foreach ($source->parameters as $parameter) {
                if (! $parameter->chart_group) {
                    continue;
                }

                $key = $parameter->chart_group;
                $groups[$key] ??= [
                    'key' => $key,
                    'title' => match ($key) {
                        'tegangan' => 'Tegangan',
                        'temperatur' => 'Temperatur',
                        'tekanan' => 'Tekanan',
                        'debit' => 'Debit air',
                        default => ucfirst(str_replace(['_', '-'], ' ', $key)),
                    },
                    'series' => [],
                ];
                $groups[$key]['series'][] = [
                    'id' => "{$source->id}:{$parameter->key}",
                    'source_id' => $source->id,
                    'key' => $parameter->key,
                    'label' => $parameter->label,
                    'source_name' => $source->name,
                    'unit' => $parameter->unit,
                ];
            }
        }

        $priority = ['tegangan' => 0, 'temperatur' => 1, 'tekanan' => 2, 'debit' => 3];
        uksort($groups, fn (string $left, string $right) =>
            ($priority[$left] ?? 100) <=> ($priority[$right] ?? 100) ?: strcmp($left, $right));

        return $groups;
    }

    /** @return array<int, array<string, mixed>> */
    public function history(Device $device, string $range): array
    {
        $groups = $this->groups($device);
        $seriesById = [];
        foreach ($groups as $group) {
            foreach ($group['series'] as $series) {
                $seriesById[$series['id']] = $group['key'];
            }
        }
        if ($seriesById === []) {
            return [];
        }

        $sourceIds = $device->sources->pluck('id')->all();
        $keys = collect($groups)->flatMap(fn ($group) => collect($group['series'])->pluck('key'))
            ->unique()->values()->all();
        $bucket = self::RANGES[$range]['bucket'];
        $since = now()->subSeconds(self::RANGES[$range]['seconds'])->utc();
        $scanStart = $since->copy()->subDay()->toDateTimeString();
        $sourcePlaceholders = implode(',', array_fill(0, count($sourceIds), '?'));
        $keyPlaceholders = implode(',', array_fill(0, count($keys), '?'));

        $rows = DB::select(<<<SQL
            SELECT si.data_source_id,
                   floor(extract(epoch from measured.observed_at) / ?)::bigint AS slot,
                   metric.key AS parameter_key,
                   sum((metric.value->>'sum')::numeric) /
                       nullif(sum((metric.value->>'count')::numeric), 0) AS value
            FROM source_intervals si
            CROSS JOIN LATERAL jsonb_each(si.metrics::jsonb) AS metric(key, value)
            CROSS JOIN LATERAL (
                SELECT coalesce((metric.value->>'recorded_at')::timestamptz,
                                si.bucket_start AT TIME ZONE 'UTC') AS observed_at
            ) measured
            WHERE si.data_source_id IN ({$sourcePlaceholders})
              AND si.bucket_start >= ?
              AND measured.observed_at >= ?::timestamptz
              AND metric.key IN ({$keyPlaceholders})
              AND coalesce((metric.value->>'count')::numeric, 0) > 0
            GROUP BY si.data_source_id, slot, metric.key
            ORDER BY slot
        SQL, [$bucket, ...$sourceIds, $scanStart, $since->toIso8601String(), ...$keys]);

        $points = [];
        foreach ($rows as $row) {
            $seriesId = "{$row->data_source_id}:{$row->parameter_key}";
            $groupKey = $seriesById[$seriesId] ?? null;
            if ($groupKey === null) {
                continue;
            }
            $slot = (int) $row->slot;
            $points[$groupKey][$slot] ??= [
                'time' => Carbon::createFromTimestampUTC($slot * $bucket)->toIso8601String(),
                'values' => [],
            ];
            $points[$groupKey][$slot]['values'][$seriesId] = round((float) $row->value, 3);
        }

        return collect($groups)->map(fn ($group) => [
            ...$group,
            'points' => array_values($points[$group['key']] ?? []),
        ])->values()->all();
    }
}
