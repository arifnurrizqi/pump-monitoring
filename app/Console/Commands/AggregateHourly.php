<?php

namespace App\Console\Commands;

use App\Models\TelemetryRaw;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateHourly extends Command
{
    protected $signature = 'aggregate:hourly {--period= : Any date/time inside the hour to aggregate}';

    protected $description = 'Aggregate telemetry_raw into telemetry_hourly';

    private const METRICS = [
        'ph', 'turbidity', 'sample_flow', 'level_percent', 'level_cm',
        'device_voltage_mcu', 'device_voltage_5v', 'device_voltage_input',
        'signal_strength',
    ];

    public function handle(): int
    {
        $period = $this->option('period')
            ? Carbon::parse($this->option('period'))
            : Carbon::now()->subHour();
        $start = $period->copy()->startOfHour();
        $end = $period->copy()->endOfHour();
        $groups = [];

        TelemetryRaw::query()
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$groups): void {
                foreach ($rows as $row) {
                    $deviceId = $row->device_id;
                    $groups[$deviceId] ??= ['usage' => 0.0, 'sample_count' => 0, 'metrics' => []];
                    $groups[$deviceId]['usage'] += (float) $row->usage_liter;
                    $groups[$deviceId]['sample_count'] += $row->sample_count ?? 1;
                    $storedMetrics = $row->raw_payload['metrics'] ?? [];

                    foreach (self::METRICS as $key) {
                        $metric = $storedMetrics[$key] ?? null;

                        if ($metric === null && $row->{$key} !== null) {
                            $value = (float) $row->{$key};
                            $metric = ['sum' => $value, 'count' => 1, 'min' => $value, 'max' => $value];
                        }

                        if ($metric === null) {
                            continue;
                        }

                        $current = $groups[$deviceId]['metrics'][$key] ?? [
                            'sum' => 0.0, 'count' => 0, 'min' => null, 'max' => null,
                        ];
                        $current['sum'] += (float) $metric['sum'];
                        $current['count'] += (int) $metric['count'];
                        $current['min'] = $current['min'] === null
                            ? (float) $metric['min'] : min($current['min'], (float) $metric['min']);
                        $current['max'] = $current['max'] === null
                            ? (float) $metric['max'] : max($current['max'], (float) $metric['max']);
                        $groups[$deviceId]['metrics'][$key] = $current;
                    }
                }
            });

        if (empty($groups)) {
            $this->warn('No raw data found for this period.');

            return self::SUCCESS;
        }

        foreach ($groups as $deviceId => $group) {
            $values = [
                'period_end' => $end,
                'usage_liter' => $group['usage'],
                'sample_count' => $group['sample_count'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            foreach (self::METRICS as $key) {
                $metric = $group['metrics'][$key] ?? null;
                $values["avg_{$key}"] = $metric && $metric['count'] > 0
                    ? $metric['sum'] / $metric['count'] : null;
                $values["min_{$key}"] = $metric['min'] ?? null;
                $values["max_{$key}"] = $metric['max'] ?? null;
            }

            DB::table('telemetry_hourly')->updateOrInsert(
                ['device_id' => $deviceId, 'period_start' => $start],
                $values,
            );
        }

        $this->info('Hourly aggregation completed successfully.');

        return self::SUCCESS;
    }
}
