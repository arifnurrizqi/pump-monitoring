<?php

namespace Database\Seeders;

use App\Models\AlarmLog;
use App\Models\DataSource;
use App\Models\Device;
use App\Models\ProductionSite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PumpDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $operator = User::where('email', 'editor@example.com')->firstOrFail();

        $definitions = [
            ['sn' => 'DEMO-PUMP-001', 'tag' => 'Pompa Sumur Utara', 'site' => 'SUMUR-UTARA', 'site_name' => 'Sumur Utara', 'location' => 'Sumur produksi Blok A', 'description' => 'Pompa 3 fasa beroperasi stabil dengan jeda perawatan berkala.', 'phase' => 3, 'age' => 0],
            ['sn' => 'DEMO-PUMP-002', 'tag' => 'Pompa Sumur Selatan', 'site' => 'SUMUR-SELATAN', 'site_name' => 'Sumur Selatan', 'location' => 'Sumur produksi Blok C', 'description' => 'Pompa 1 fasa: debit menurun dan temperatur motor naik sebelum koneksi terputus.', 'phase' => 1, 'age' => 1800],
            ['sn' => 'DEMO-PUMP-003', 'tag' => 'Pompa Booster', 'site' => 'BOOSTER', 'site_name' => 'Stasiun Booster', 'location' => 'Rumah pompa distribusi', 'description' => 'Booster 3 fasa hidup-mati mengikuti kebutuhan tekanan jaringan.', 'phase' => 3, 'age' => 130],
        ];

        foreach ($definitions as $index => $definition) {
            $site = ProductionSite::updateOrCreate(
                ['code' => $definition['site']],
                ['name' => $definition['site_name'], 'location' => $definition['location']],
            );
            $device = Device::updateOrCreate(
                ['device_sn' => $definition['sn']],
                [
                    'device_tag' => $definition['tag'],
                    'production_site_id' => $site->id,
                    'location' => $definition['location'],
                    'description' => $definition['description'],
                    'is_active' => true,
                    'last_seen_at' => now()->subSeconds($definition['age']),
                ],
            );
            $device->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
            if ($index === 0) {
                $device->users()->syncWithoutDetaching([$operator->id => ['role' => 'operator']]);
            }
            $device->setting()->updateOrCreate(['device_id' => $device->id], [
                'send_interval_sec' => 10,
            ]);

            $asset = $site->assets()->updateOrCreate(
                ['name' => $definition['tag']],
                ['type' => 'pump', 'phase_count' => $definition['phase']],
            );
            $power = $device->sources()->updateOrCreate(
                ['source_key' => 'PM-01'],
                ['name' => 'Power Meter', 'type' => 'power_meter', 'monitored_asset_id' => $asset->id, 'is_active' => true],
            );
            $motor = $device->sources()->updateOrCreate(
                ['source_key' => 'MOTOR-01'],
                ['name' => 'Sensor Motor & Proses', 'type' => 'other', 'monitored_asset_id' => $asset->id, 'is_active' => true],
            );

            $powerDefinitions = [];
            for ($phase = 1; $phase <= $definition['phase']; $phase++) {
                $powerDefinitions["voltage_l{$phase}"] = ["Tegangan L{$phase}", 'V', 227 + $phase];
                $powerDefinitions["current_l{$phase}"] = ["Arus L{$phase}", 'A', 9 + $phase * 0.2];
            }
            $powerDefinitions += [
                'active_power' => ['Daya aktif', 'kW', $definition['phase'] === 3 ? 5.4 : 2.1],
                'power_factor' => ['Faktor daya', null, 0.91],
                'frequency' => ['Frekuensi', 'Hz', 50],
                'energy_total' => ['Energi total', 'kWh', 1264 + $index * 380],
            ];
            $position = 0;
            foreach ($powerDefinitions as $key => [$label, $unit, $base]) {
                $power->parameters()->updateOrCreate(['key' => $key], [
                    'label' => $label, 'unit' => $unit, 'sort_order' => $position++,
                    'chart_group' => str_starts_with($key, 'voltage_') ? 'tegangan' : null,
                    'alarm_enabled' => str_starts_with($key, 'voltage_') || str_starts_with($key, 'current_'),
                    'alarm_min' => str_starts_with($key, 'voltage_') ? 200 : null,
                    'alarm_max' => str_starts_with($key, 'voltage_') ? 245 : (str_starts_with($key, 'current_') ? 20 : null),
                ]);
            }
            $motorDefinitions = [
                'motor_temp' => ['Temperatur motor', '°C', $index === 1 ? 84 : 58, 80],
                'pressure' => ['Tekanan discharge', 'bar', 3.6, 6],
                'flow_rate' => ['Debit', 'm³/h', 22, null],
            ];
            $position = 0;
            foreach ($motorDefinitions as $key => [$label, $unit, $base, $max]) {
                $motor->parameters()->updateOrCreate(['key' => $key], [
                    'label' => $label, 'unit' => $unit, 'sort_order' => $position++,
                    'chart_group' => match ($key) {
                        'motor_temp' => 'temperatur',
                        'pressure' => 'tekanan',
                        'flow_rate' => 'debit',
                        default => null,
                    },
                    'alarm_enabled' => $max !== null, 'alarm_max' => $max,
                ]);
            }

            $latest = $this->seedHistory($index, $definition['age'], $power, $motor);
            Cache::store(config('mqtt.live_cache_store'))->forget("telemetry:v2:{$device->id}");

            AlarmLog::where('device_id', $device->id)->where('alarm_code', 'demo_pump_motor_hot')->delete();
            if ($index === 1) {
                $temperature = $motor->parameters()->where('key', 'motor_temp')->firstOrFail();
                AlarmLog::create([
                    'device_id' => $device->id,
                    'source_parameter_id' => $temperature->id,
                    'alarm_code' => 'demo_pump_motor_hot',
                    'parameter_name' => 'motor_temp',
                    'alarm_message' => 'Sensor Motor & Proses: temperatur motor tinggi',
                    'current_value' => $latest['motor']['motor_temp'],
                    'threshold_value' => 80,
                    'severity' => 'warning',
                    'status' => 'active',
                    'started_at' => now()->subSeconds($definition['age'])->subHours(2),
                ]);
            }
        }
    }

    /** @return array{power: array<string, float>, motor: array<string, float>} */
    private function seedHistory(int $profile, int $lagSeconds, DataSource $power, DataSource $motor): array
    {
        $anchor = now()->utc()->subSeconds($lagSeconds)->startOfMinute();
        $createdAt = now()->utc()->toDateTimeString();
        $latest = [];

        DB::transaction(function () use ($profile, $power, $motor, $anchor, $createdAt, &$latest): void {
            DB::table('source_intervals')->whereIn('data_source_id', [$power->id, $motor->id])->delete();
            $rows = [];

            foreach ($this->sampleAges() as $minutesAgo) {
                $time = $anchor->copy()->subMinutes($minutesAgo);
                $latest = $this->readings($profile, $minutesAgo, $time);
                foreach (['power' => $power, 'motor' => $motor] as $kind => $source) {
                    $metrics = [];
                    foreach ($latest[$kind] as $key => $value) {
                        $metrics[$key] = $this->metric($key, $value, $time, $minutesAgo);
                    }
                    $rows[] = [
                        'data_source_id' => $source->id,
                        'bucket_start' => $time->toDateTimeString(),
                        'last_recorded_at' => $time->toDateTimeString(),
                        'sample_count' => 6,
                        'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR),
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }
                if (count($rows) >= 500) {
                    DB::table('source_intervals')->insert($rows);
                    $rows = [];
                }
            }
            if ($rows !== []) {
                DB::table('source_intervals')->insert($rows);
            }
        });

        return $latest;
    }

    /** @return \Generator<int> */
    private function sampleAges(): \Generator
    {
        foreach ([[43200, 10080, 30], [10080, 1440, 10], [1440, 60, 2], [60, 0, 1]] as [$from, $until, $step]) {
            for ($minute = $from; $minute > $until; $minute -= $step) {
                yield $minute;
            }
        }
        yield 0;
    }

    /** @return array{power: array<string, float>, motor: array<string, float>} */
    private function readings(int $profile, int $minutesAgo, Carbon $time): array
    {
        $local = $time->copy()->timezone(config('app.timezone'));
        $hour = $local->hour + $local->minute / 60;
        $daily = sin(2 * M_PI * ($hour - 6) / 24);
        $slow = sin($minutesAgo / 610 + $profile * 1.3);
        $short = sin($minutesAgo / 17 + $profile * 2.1);
        $grid = 1.1 * $daily + 1.2 * $slow + 0.55 * $short;
        $running = true;
        $fault = 0.0;

        if ($profile === 0) {
            $running = ! ($local->dayOfWeekIso === 7 && $hour >= 9 && $hour < 10.5)
                && ! ($minutesAgo >= 5400 && $minutesAgo < 5460);
            $fault = max(0, 1 - abs($minutesAgo - 30) / 18);
            $flow = $running ? 22.4 + 1.2 * $slow + 0.5 * $short - 2.5 * $fault : 0.0;
            $pressure = $running ? 3.7 + 0.18 * $slow + 0.08 * $short - 0.4 * $fault : 0.6;
            $temperature = $running ? 57.5 + 2.3 * $daily + 1.1 * $slow + 0.8 * $fault : 36.0 + 1.2 * $daily;
            $powerKw = $running ? 5.3 + 0.22 * $slow + 0.12 * $short : 0.0;
            $current = $running ? 9.0 + 0.45 * $slow + 0.18 * $short : 0.0;
            $voltage = 229.0 + $grid - ($running ? 0.8 : 0.0);
        } elseif ($profile === 1) {
            // Saringan tersumbat selama 12 jam: beban dan suhu naik, debit dan tekanan turun.
            $fault = min(1, max(0, (720 - $minutesAgo) / 720));
            $flow = 19.8 + 1.0 * $slow + 0.35 * $short - 7.2 * $fault;
            $pressure = 3.2 + 0.12 * $slow - 0.85 * $fault;
            $temperature = 57.0 + 2.0 * $daily + 1.0 * $slow + 29.0 * $fault;
            $powerKw = 2.05 + 0.1 * $short + 0.48 * $fault;
            $current = 10.6 + 0.4 * $slow + 3.0 * $fault;
            $voltage = 226.0 + $grid - 1.8 * $fault;
        } else {
            // Booster berjalan 60 menit dan berhenti 30 menit untuk menjaga tekanan distribusi.
            $cycle = (43200 - $minutesAgo + 25) % 90;
            $running = $cycle < 60;
            $flow = $running ? 30.5 + 1.8 * $slow + 0.7 * $short : 0.0;
            $pressure = $running ? 4.4 + 0.24 * $slow + 0.08 * $short : 2.85 - 0.01 * ($cycle - 60);
            $temperature = $running ? 47.0 + 0.22 * $cycle + 0.8 * $daily
                : 60.0 - 0.45 * ($cycle - 60) + 0.8 * $daily;
            $powerKw = $running ? 6.0 + 0.3 * $slow + 0.18 * $short : 0.04;
            $current = $running ? 10.1 + 0.55 * $slow + 0.2 * $short : 0.1;
            $voltage = 230.0 + $grid - ($running ? 1.2 : 0.0);
        }

        $elapsedMinutes = 43200 - $minutesAgo;
        $power = [];
        foreach (range(1, $profile === 1 ? 1 : 3) as $phase) {
            $phaseOffset = match ($phase) { 1 => 0.0, 2 => -2.1, 3 => 1.4 };
            $power["voltage_l{$phase}"] = round($voltage + $phaseOffset + 0.25 * sin($minutesAgo / 11 + $phase), 2);
            $power["current_l{$phase}"] = round(max(0, $current + ($running ? 0.13 * ($phase - 2) : 0)), 2);
        }
        $power['active_power'] = round(max(0, $powerKw), 2);
        $power['power_factor'] = $running ? round(0.9 + 0.015 * $slow, 3) : 0.0;
        $power['frequency'] = round(50 + 0.035 * $short, 3);
        $power['energy_total'] = round(1264 + $profile * 380 + $elapsedMinutes * [5.1, 2.15, 4.1][$profile] / 60, 2);

        return [
            'power' => $power,
            'motor' => [
                'motor_temp' => round($temperature, 2),
                'pressure' => round(max(0, $pressure), 2),
                'flow_rate' => round(max(0, $flow), 2),
            ],
        ];
    }

    /** @return array<string, float|int|string> */
    private function metric(string $key, float $value, Carbon $time, int $minutesAgo): array
    {
        $spread = match (true) {
            str_starts_with($key, 'voltage_') => 0.6,
            str_starts_with($key, 'current_') => 0.15,
            $key === 'motor_temp' => 0.25,
            $key === 'pressure' => 0.04,
            $key === 'flow_rate' => 0.2,
            $key === 'energy_total' => 0.0,
            default => 0.03,
        };
        $spread = $value == 0.0 ? 0.0 : $spread;

        return [
            'sum' => round($value * 6, 3),
            'count' => 6,
            'min' => round(max(0, $value - $spread), 3),
            'max' => round($value + $spread, 3),
            'last' => round(max(0, $value + $spread * sin($minutesAgo / 3)), 3),
            'quality' => 'good',
            'recorded_at' => $time->toIso8601String(),
        ];
    }
}
