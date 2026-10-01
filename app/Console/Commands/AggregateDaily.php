<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateDaily extends Command
{
    protected $signature = 'aggregate:daily {--period= : Any date inside the day to aggregate}';

    protected $description = 'Aggregate telemetry_hourly into telemetry_daily';

    public function handle()
    {
        $period = $this->option('period')
            ? Carbon::parse($this->option('period'))
            : Carbon::now()->subDay();
        $start = $period->copy()->startOfDay();
        $end = $period->copy()->endOfDay();

        $this->info("Start daily aggregation: $start - $end");

        $rows = DB::table('telemetry_hourly')
            ->whereBetween('period_start', [$start, $end])
            ->selectRaw('
                device_id,

                -- WEIGHTED AVG
                SUM(avg_ph * sample_count) / NULLIF(SUM(CASE WHEN avg_ph IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_ph,
                MIN(min_ph) as min_ph,
                MAX(max_ph) as max_ph,

                SUM(avg_turbidity * sample_count) / NULLIF(SUM(CASE WHEN avg_turbidity IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_turbidity,
                MIN(min_turbidity) as min_turbidity,
                MAX(max_turbidity) as max_turbidity,

                SUM(avg_sample_flow * sample_count) / NULLIF(SUM(CASE WHEN avg_sample_flow IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_sample_flow,
                MIN(min_sample_flow) as min_sample_flow,
                MAX(max_sample_flow) as max_sample_flow,

                SUM(usage_liter) as usage_liter,

                SUM(avg_level_percent * sample_count) / NULLIF(SUM(CASE WHEN avg_level_percent IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_level_percent,
                MIN(min_level_percent) as min_level_percent,
                MAX(max_level_percent) as max_level_percent,

                SUM(avg_level_cm * sample_count) / NULLIF(SUM(CASE WHEN avg_level_cm IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_level_cm,
                MIN(min_level_cm) as min_level_cm,
                MAX(max_level_cm) as max_level_cm,

                SUM(avg_device_voltage_mcu * sample_count) / NULLIF(SUM(CASE WHEN avg_device_voltage_mcu IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_device_voltage_mcu,
                MIN(min_device_voltage_mcu) as min_device_voltage_mcu,
                MAX(max_device_voltage_mcu) as max_device_voltage_mcu,

                SUM(avg_device_voltage_5v * sample_count) / NULLIF(SUM(CASE WHEN avg_device_voltage_5v IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_device_voltage_5v,
                MIN(min_device_voltage_5v) as min_device_voltage_5v,
                MAX(max_device_voltage_5v) as max_device_voltage_5v,

                SUM(avg_device_voltage_input * sample_count) / NULLIF(SUM(CASE WHEN avg_device_voltage_input IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_device_voltage_input,
                MIN(min_device_voltage_input) as min_device_voltage_input,
                MAX(max_device_voltage_input) as max_device_voltage_input,

                SUM(avg_signal_strength * sample_count) / NULLIF(SUM(CASE WHEN avg_signal_strength IS NOT NULL THEN sample_count ELSE 0 END), 0) as avg_signal_strength,
                MIN(min_signal_strength) as min_signal_strength,
                MAX(max_signal_strength) as max_signal_strength,

                SUM(sample_count) as sample_count
            ')
            ->groupBy('device_id')
            ->get();

        if ($rows->isEmpty()) {
            $this->warn('No hourly data found.');
            \Log::warning("Daily aggregation skipped ($start)");

            return 0;
        }

        foreach ($rows as $row) {

            DB::table('telemetry_daily')->updateOrInsert(
                [
                    'device_id' => $row->device_id,
                    'period_start' => $start,
                ],
                [
                    'period_end' => $end,

                    'avg_ph' => $row->avg_ph,
                    'min_ph' => $row->min_ph,
                    'max_ph' => $row->max_ph,

                    'avg_turbidity' => $row->avg_turbidity,
                    'min_turbidity' => $row->min_turbidity,
                    'max_turbidity' => $row->max_turbidity,

                    'avg_sample_flow' => $row->avg_sample_flow,
                    'min_sample_flow' => $row->min_sample_flow,
                    'max_sample_flow' => $row->max_sample_flow,
                    'usage_liter' => $row->usage_liter,

                    'avg_level_percent' => $row->avg_level_percent,
                    'min_level_percent' => $row->min_level_percent,
                    'max_level_percent' => $row->max_level_percent,

                    'avg_level_cm' => $row->avg_level_cm,
                    'min_level_cm' => $row->min_level_cm,
                    'max_level_cm' => $row->max_level_cm,

                    'avg_device_voltage_mcu' => $row->avg_device_voltage_mcu,
                    'min_device_voltage_mcu' => $row->min_device_voltage_mcu,
                    'max_device_voltage_mcu' => $row->max_device_voltage_mcu,

                    'avg_device_voltage_5v' => $row->avg_device_voltage_5v,
                    'min_device_voltage_5v' => $row->min_device_voltage_5v,
                    'max_device_voltage_5v' => $row->max_device_voltage_5v,

                    'avg_device_voltage_input' => $row->avg_device_voltage_input,
                    'min_device_voltage_input' => $row->min_device_voltage_input,
                    'max_device_voltage_input' => $row->max_device_voltage_input,

                    'avg_signal_strength' => $row->avg_signal_strength,
                    'min_signal_strength' => $row->min_signal_strength,
                    'max_signal_strength' => $row->max_signal_strength,

                    'sample_count' => $row->sample_count,

                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->info('Daily aggregation completed.');
        \Log::info("Daily aggregation success ($start)");

        return 0;
    }
}
