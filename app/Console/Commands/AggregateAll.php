<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AggregateAll extends Command
{
    protected $signature = 'aggregate:all';

    protected $description = 'Aggregate all telemetry data sequentially from hourly through yearly';

    /**
     * @var array<string, array{source: string, column: string, format: string}>
     */
    private const LEVELS = [
        'hourly' => ['source' => 'telemetry_raw', 'column' => 'recorded_at', 'format' => 'Y-m-d H:00:00'],
        'daily' => ['source' => 'telemetry_hourly', 'column' => 'period_start', 'format' => 'Y-m-d 00:00:00'],
        'monthly' => ['source' => 'telemetry_daily', 'column' => 'period_start', 'format' => 'Y-m-01 00:00:00'],
        'yearly' => ['source' => 'telemetry_monthly', 'column' => 'period_start', 'format' => 'Y-01-01 00:00:00'],
    ];

    public function handle(): int
    {
        $this->info('Starting full database aggregation (hourly -> daily -> monthly -> yearly).');

        foreach (self::LEVELS as $level => $config) {
            $periods = $this->periodsFrom($config['source'], $config['column'], $config['format']);

            if ($periods === []) {
                $this->warn("Skipping $level: no source data found.");

                continue;
            }

            $this->newLine();
            $this->info(sprintf('Processing %d %s period(s)...', count($periods), $level));
            $bar = $this->output->createProgressBar(count($periods));
            $bar->start();

            foreach ($periods as $period) {
                $exitCode = $this->callSilently("aggregate:$level", ['--period' => $period]);

                if ($exitCode !== self::SUCCESS) {
                    $bar->finish();
                    $this->newLine(2);
                    $this->error("Aggregation failed at $level period $period.");

                    return self::FAILURE;
                }

                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
        }

        $this->newLine();
        $this->info('Full database aggregation completed successfully.');

        return self::SUCCESS;
    }

    /**
     * Read source timestamps as a cursor so raw telemetry is not loaded into memory at once.
     *
     * @return list<string>
     */
    private function periodsFrom(string $table, string $column, string $format): array
    {
        $periods = [];

        foreach (DB::table($table)->whereNotNull($column)->orderBy($column)->cursor() as $row) {
            $periods[Carbon::parse($row->{$column})->format($format)] = true;
        }

        return array_keys($periods);
    }
}
