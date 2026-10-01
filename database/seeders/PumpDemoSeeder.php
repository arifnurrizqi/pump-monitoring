<?php

namespace Database\Seeders;

use App\Models\AlarmLog;
use App\Models\Device;
use App\Models\ProductionSite;
use App\Models\User;
use Illuminate\Database\Seeder;

class PumpDemoSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $operator = User::where('email', 'editor@example.com')->firstOrFail();

        $definitions = [
            ['sn' => 'DEMO-PUMP-001', 'tag' => 'Pompa Sumur Utara', 'site' => 'SUMUR-UTARA', 'site_name' => 'Sumur Utara', 'phase' => 3, 'age' => 0],
            ['sn' => 'DEMO-PUMP-002', 'tag' => 'Pompa Sumur Selatan', 'site' => 'SUMUR-SELATAN', 'site_name' => 'Sumur Selatan', 'phase' => 1, 'age' => 1800],
            ['sn' => 'DEMO-PUMP-003', 'tag' => 'Pompa Booster', 'site' => 'BOOSTER', 'site_name' => 'Stasiun Booster', 'phase' => 3, 'age' => 130],
        ];

        foreach ($definitions as $index => $definition) {
            $site = ProductionSite::updateOrCreate(
                ['code' => $definition['site']],
                ['name' => $definition['site_name'], 'location' => 'Area produksi'],
            );
            $device = Device::updateOrCreate(
                ['device_sn' => $definition['sn']],
                [
                    'device_tag' => $definition['tag'],
                    'api_token' => 'demo-pump-token-'.($index + 1),
                    'production_site_id' => $site->id,
                    'location' => $definition['site_name'],
                    'description' => 'Gateway demo monitoring pompa',
                    'is_active' => true,
                    'last_seen_at' => now()->subSeconds($definition['age']),
                ],
            );
            $device->users()->syncWithoutDetaching([$owner->id => ['role' => 'owner']]);
            if ($index === 0) {
                $device->users()->syncWithoutDetaching([$operator->id => ['role' => 'operator']]);
            }
            $device->setting()->updateOrCreate(['device_id' => $device->id], [
                'send_interval_sec' => 10, 'timezone' => 'Asia/Jakarta', 'alarm_enabled' => true,
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
                $powerDefinitions["voltage_l{$phase}"] = ["Tegangan L{$phase}", 'V', 210 + ($phase * 2)];
                $powerDefinitions["current_l{$phase}"] = ["Arus L{$phase}", 'A', 10 + $phase];
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
                    'alarm_enabled' => $max !== null, 'alarm_max' => $max,
                ]);
            }

            foreach ([[$power, $powerDefinitions], [$motor, $motorDefinitions]] as [$source, $parameters]) {
                $source->intervals()->delete();
                for ($minute = 59; $minute >= 0; $minute--) {
                    $time = now()->subSeconds($definition['age'])->subMinutes($minute)->startOfMinute();
                    $metrics = [];
                    foreach ($parameters as $key => $parameter) {
                        $base = $parameter[2];
                        $value = round($base + sin(($minute + $index * 9) / 6) * ($base > 100 ? 2.1 : ($base > 10 ? 0.8 : 0.12)), 2);
                        $metrics[$key] = [
                            'sum' => $value, 'count' => 1, 'min' => $value,
                            'max' => $value, 'last' => $value, 'quality' => 'good',
                            'recorded_at' => $time->toIso8601String(),
                        ];
                    }
                    $source->intervals()->create([
                        'bucket_start' => $time, 'last_recorded_at' => $time,
                        'sample_count' => 1, 'metrics' => $metrics,
                    ]);
                }
            }

            AlarmLog::where('device_id', $device->id)->where('alarm_code', 'demo_pump_motor_hot')->delete();
            if ($index === 1) {
                $temperature = $motor->parameters()->where('key', 'motor_temp')->firstOrFail();
                AlarmLog::create([
                    'device_id' => $device->id,
                    'source_parameter_id' => $temperature->id,
                    'alarm_code' => 'demo_pump_motor_hot',
                    'parameter_name' => 'motor_temp',
                    'alarm_message' => 'Sensor Motor & Proses: temperatur motor tinggi',
                    'current_value' => 84,
                    'threshold_value' => 80,
                    'severity' => 'warning',
                    'status' => 'active',
                    'started_at' => now()->subMinutes(30),
                ]);
            }
        }
    }
}
