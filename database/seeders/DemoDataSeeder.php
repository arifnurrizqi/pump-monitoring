<?php

namespace Database\Seeders;

use App\Models\AlarmLog;
use App\Models\Device;
use App\Models\DeviceSetting;
use App\Models\TelemetryDaily;
use App\Models\TelemetryHourly;
use App\Models\TelemetryLatest;
use App\Models\TelemetryMonthly;
use App\Models\TelemetryRaw;
use App\Models\TelemetryYearly;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // Keep sensor patterns reproducible while timestamps remain relative to seed time.
        mt_srand(20260824);

        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $editor = User::where('email', 'editor@example.com')->firstOrFail();

        $devices = collect([
            [
                'device_sn' => 'DEMO-DEVICE-001',
                'device_tag' => 'Demo Water Tank A',
                'location' => 'Plant A',
                'description' => 'Primary demo device with owner/editor access',
                'is_active' => true,
            ],
            [
                'device_sn' => 'DEMO-DEVICE-002',
                'device_tag' => 'Demo Water Tank B',
                'location' => 'Plant B',
                'description' => 'Demo device for telemetry and alarm coverage',
                'is_active' => true,
            ],
            [
                'device_sn' => 'DEMO-DEVICE-003',
                'device_tag' => 'Demo Water Tank C',
                'location' => 'Plant C',
                'description' => 'Demo device without alarms',
                'is_active' => true,
            ],
        ])->map(function (array $definition, int $index): Device {
            $offsetSeconds = match ($index) {
                0 => 0,       // Tank A: online
                1 => 1800,    // Tank B: offline (> 180s)
                2 => 130,     // Tank C: pending (between 90s and 180s)
            };

            return Device::updateOrCreate(
                ['device_sn' => $definition['device_sn']],
                array_merge($definition, [
                    'api_token' => 'demo-token-'.($index + 1),
                    'installed_at' => now()->subDays(45 - ($index * 5)),
                    'last_seen_at' => now()->subSeconds($offsetSeconds),
                ])
            );
        });

        $devices->each(function (Device $device, int $index) use ($owner, $editor) {
            $device->users()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner'],
            ]);

            if ($index === 0) {
                $device->users()->syncWithoutDetaching([
                    $editor->id => ['role' => 'operator'],
                ]);
            }
        });

        $devices->each(function (Device $device) {
            DeviceSetting::updateOrCreate(
                ['device_id' => $device->id],
                [
                    'tank_height_cm' => 150,
                    'tank_volume_liter' => 1200,
                    'ph_min' => 6.5,
                    'ph_max' => 8.5,
                    'pulse_per_liter' => 450,
                    'turbidity_max' => 25,
                    'sample_flow_min' => 0.5,
                    'sample_flow_max' => 18,
                    'level_min_percent' => 20,
                    'level_max_percent' => 90,
                    'device_voltage_mcu_min' => 3.20,
                    'device_voltage_5v_min' => 4.80,
                    'device_voltage_input_min' => 11.50,
                    'sampling_interval_sec' => 10,
                    'send_interval_sec' => 10,
                    'alarm_enabled' => true,
                    'timezone' => 'Asia/Jakarta',
                    'notes' => 'Demo settings',
                ]
            );
        });

        $deviceIds = $devices->pluck('id')->all();

        DB::transaction(function () use ($devices, $deviceIds, $owner) {
            TelemetryLatest::whereIn('device_id', $deviceIds)->delete();
            TelemetryHourly::whereIn('device_id', $deviceIds)->delete();
            TelemetryDaily::whereIn('device_id', $deviceIds)->delete();
            TelemetryMonthly::whereIn('device_id', $deviceIds)->delete();
            TelemetryYearly::whereIn('device_id', $deviceIds)->delete();
            TelemetryRaw::whereIn('device_id', $deviceIds)->delete();
            AlarmLog::whereIn('device_id', $deviceIds)->delete();

            $devices->each(function (Device $device, int $deviceIndex) {
                $rows = collect();

                // State variables to generate realistic fluctuations
                $ph = 7.2 + ($deviceIndex * 0.1);
                $turbiditySpike = 0;
                $levelPercent = 75 - ($deviceIndex * 5); // start around 70-75%
                $pumpActive = false;

                $offsetSeconds = match ($deviceIndex) {
                    0 => 0,       // Tank A: online
                    1 => 1800,    // Tank B: offline (> 180s)
                    2 => 130,     // Tank C: pending (between 90s and 180s)
                };

                $now = now();
                $rangeStart = $now->copy()->subDays(30);
                $timestamps = collect();

                // 1. From 30 days ago to 7 hours ago: every 1 hour
                for ($i = 720; $i > 7; $i--) {
                    $timestamp = $now->copy()->subHours($i)->subSeconds($offsetSeconds);

                    if ($timestamp->gte($rangeStart)) {
                        $timestamps->push($timestamp);
                    }
                }

                // 2. From 7 hours ago to 1 hour ago: every 30 minutes
                for ($m = 420; $m > 60; $m -= 30) {
                    $timestamps->push(
                        $now->copy()->subMinutes($m)->subSeconds($offsetSeconds)
                    );
                }

                // 3. From 1 hour ago to now: every 1 minute
                for ($m = 60; $m >= 0; $m--) {
                    $timestamps->push(
                        $now->copy()->subMinutes($m)->subSeconds($offsetSeconds)
                    );
                }

                $prevRecordedAt = null;
                foreach ($timestamps as $loopIndex => $recordedAt) {
                    $hourOfDay = (int) $recordedAt->format('H');

                    // Calculate time difference in minutes to scale flows/changes correctly
                    if ($prevRecordedAt === null) {
                        $deltaMinutes = 60.0;
                    } else {
                        $deltaMinutes = $prevRecordedAt->diffInMinutes($recordedAt, true);
                    }
                    $prevRecordedAt = $recordedAt;
                    $intervalFactor = $deltaMinutes / 60.0;

                    if ($deviceIndex === 0) {
                        // DEVICE 0: ALL NORMAL
                        // Normal ranges: ph (6.5-8.5), turbidity (<=25), sample flow (0.5-18), level (20-90), voltage_mcu (>=3.2), voltage_5v (>=4.8), voltage_input (>=11.5)
                        $sampleFlow = round(4.5 + sin($loopIndex) * 1.5 + rand(-5, 5) / 10, 2);
                        $sampleFlow = max(1.0, min(8.0, $sampleFlow));

                        if ($pumpActive) {
                            $levelPercent += (20.0 - ($sampleFlow * 0.4)) * $intervalFactor;
                            if ($levelPercent >= 80.0) {
                                $pumpActive = false;
                            }
                        } else {
                            $levelPercent -= ($sampleFlow * 0.5) * $intervalFactor;
                            if ($levelPercent <= 40.0) {
                                $pumpActive = true;
                            }
                        }
                        $levelPercent = round(max(38.0, min(82.0, $levelPercent + rand(-2, 2) / 10)), 2);
                        $levelCm = round($levelPercent * 1.5, 2);

                        $phStep = (rand(-3, 3) / 100) * ($intervalFactor > 0 ? sqrt($intervalFactor) : 1);
                        $ph += $phStep;
                        $ph = round(max(6.8, min(7.6, $ph)), 2);

                        $turbidity = round(3.5 + rand(-5, 5) / 10, 2);

                        $angle = 2 * 3.14159 * ($hourOfDay - 6) / 24;
                        $voltageInput = round(12.8 + 0.6 * sin($angle) + rand(-1, 1) / 10, 2);
                        $voltageMCU = round(3.30 + rand(-1, 1) / 100, 2);
                        $voltage5V = round(5.00 + rand(-1, 1) / 100, 2);
                    } elseif ($deviceIndex === 1) {
                        // DEVICE 1: ALL OUT OF BOUNDS
                        // Out of bounds: ph (>8.5), turbidity (>25), sample flow (>18), level (<20), voltage_mcu (<3.2), voltage_5v (<4.8), voltage_input (<11.5)
                        $sampleFlow = round(19.5 + rand(5, 15) / 10, 2);

                        $levelPercent = round(12.5 + rand(-5, 5) / 10, 2);
                        $levelCm = round($levelPercent * 1.5, 2);

                        $ph = round(9.2 + rand(-2, 2) / 10, 2);

                        $turbidity = round(35.5 + rand(-10, 10) / 10, 2);

                        $voltageInput = round(10.5 + rand(-2, 2) / 10, 2);
                        $voltageMCU = round(2.95 + rand(-5, 5) / 100, 2);
                        $voltage5V = round(4.50 + rand(-5, 5) / 100, 2);
                    } else {
                        // DEVICE 2: MIXED
                        // Normal: ph, sample flow, voltage_mcu, voltage_5v, voltage_input
                        // Out of bounds: turbidity, level
                        $sampleFlow = round(5.2 + rand(-5, 5) / 10, 2);

                        $levelPercent = round(14.2 + rand(-5, 5) / 10, 2);
                        $levelCm = round($levelPercent * 1.5, 2);

                        $ph = round(7.15 + rand(-2, 2) / 10, 2);

                        $turbidity = round(32.8 + rand(-15, 15) / 10, 2);

                        $voltageInput = round(12.6 + rand(-2, 2) / 10, 2);
                        $voltageMCU = round(3.30 + rand(-2, 2) / 100, 2);
                        $voltage5V = round(5.02 + rand(-2, 2) / 100, 2);
                    }

                    $levelDistanceCm = round(max(0, 150 - $levelCm), 2);
                    $signalStrength = -65 + rand(-5, 5);
                    $pulsePerLiter = 450.0;
                    $usageRatePerMinute = match (true) {
                        $hourOfDay < 5 => 0.35,
                        $hourOfDay < 9 => 5.80,
                        $hourOfDay < 16 => 3.20,
                        $hourOfDay < 22 => 6.40,
                        default => 1.20,
                    };
                    $deviceUsageFactor = [1.0, 1.28, 0.76][$deviceIndex];
                    $weekendFactor = $recordedAt->isWeekend() ? 0.68 : 1.0;
                    $variation = rand(85, 115) / 100;
                    $usageLiter = round(
                        max(0, $usageRatePerMinute * $deviceUsageFactor * $weekendFactor * $variation * $deltaMinutes),
                        6
                    );
                    $deltaPulse = (int) round($usageLiter * $pulsePerLiter);
                    $valveState = $loopIndex % 97 === 0 && $recordedAt->lt($now->copy()->subMinutes(5));

                    $statusCode = $this->qualityStatus($ph, $turbidity);

                    $rows->push(TelemetryRaw::create([
                        'device_id' => $device->id,
                        'ph' => $ph,
                        'turbidity' => $turbidity,
                        'delta_pulse' => $deltaPulse,
                        'pulse_per_liter_used' => $pulsePerLiter,
                        'usage_liter' => $usageLiter,
                        'sample_flow' => $sampleFlow,
                        'level_distance_cm' => $levelDistanceCm,
                        'level_percent' => $levelPercent,
                        'level_cm' => $levelCm,
                        'device_voltage_mcu' => $voltageMCU,
                        'device_voltage_5v' => $voltage5V,
                        'device_voltage_input' => $voltageInput,
                        'signal_strength' => $signalStrength,
                        'status_code' => $statusCode,
                        'alarm_code' => null,
                        'valve_state' => $valveState,
                        'raw_payload' => [
                            'source' => 'demo-seeder',
                            'device_sn' => $device->device_sn,
                            'sequence' => $loopIndex + 1,
                            'ph' => $ph,
                            'turbidity' => $turbidity,
                            'delta_pulse' => $deltaPulse,
                            'sample_flow' => $sampleFlow,
                            'level_distance_cm' => $levelDistanceCm,
                            'device_voltage_mcu' => $voltageMCU,
                            'device_voltage_5v' => $voltage5V,
                            'device_voltage_input' => $voltageInput,
                            'signal_strength' => $signalStrength,
                            'valve_state' => $valveState,
                            'recorded_at' => $recordedAt->toISOString(),
                        ],
                        'recorded_at' => $recordedAt,
                    ]));
                }

                $this->seedTelemetryLatest($device, $rows);
                $this->seedTelemetryAggregates($device, $rows);
            });

            $alarmDefinitions = [
                // Device 0: Resolved alarm (historical only)
                [
                    'device_index' => 0,
                    'alarm_code' => 'PH_HIGH',
                    'parameter_name' => 'ph',
                    'alarm_message' => 'pH water melebihi batas normal (Resolved)',
                    'current_value' => 8.9,
                    'threshold_value' => 8.5,
                    'severity' => 'warning',
                    'status' => 'resolved',
                    'started_at' => now()->subDays(12),
                    'resolved_at' => now()->subDays(11),
                ],
                // Device 1: Active alarms for everything out of bounds
                [
                    'device_index' => 1,
                    'alarm_code' => 'PH_HIGH',
                    'parameter_name' => 'ph',
                    'alarm_message' => 'pH water melebihi batas normal',
                    'current_value' => 9.2,
                    'threshold_value' => 8.5,
                    'severity' => 'warning',
                    'status' => 'active',
                    'started_at' => now()->subDays(12),
                    'resolved_at' => null,
                ],
                [
                    'device_index' => 1,
                    'alarm_code' => 'TURBIDITY_HIGH',
                    'parameter_name' => 'turbidity',
                    'alarm_message' => 'Kekeruhan air tinggi',
                    'current_value' => 35.5,
                    'threshold_value' => 25.0,
                    'severity' => 'critical',
                    'status' => 'active',
                    'started_at' => now()->subDays(18),
                    'resolved_at' => null,
                ],
                [
                    'device_index' => 1,
                    'alarm_code' => 'LEVEL_LOW',
                    'parameter_name' => 'level_percent',
                    'alarm_message' => 'Level air terlalu rendah',
                    'current_value' => 12.5,
                    'threshold_value' => 20.0,
                    'severity' => 'warning',
                    'status' => 'active',
                    'started_at' => now()->subDays(4),
                    'resolved_at' => null,
                ],
                [
                    'device_index' => 1,
                    'alarm_code' => 'SAMPLE_FLOW_HIGH',
                    'parameter_name' => 'sample_flow',
                    'alarm_message' => 'Debit aliran terlalu tinggi',
                    'current_value' => 19.5,
                    'threshold_value' => 18.0,
                    'severity' => 'warning',
                    'status' => 'active',
                    'started_at' => now()->subDays(9),
                    'resolved_at' => null,
                ],
                [
                    'device_index' => 1,
                    'alarm_code' => 'VOLTAGE_LOW',
                    'parameter_name' => 'device_voltage_input',
                    'alarm_message' => 'Tegangan input perangkat rendah',
                    'current_value' => 10.5,
                    'threshold_value' => 11.5,
                    'severity' => 'critical',
                    'status' => 'active',
                    'started_at' => now()->subDays(2),
                    'resolved_at' => null,
                ],
                // Device 2 (Mixed): Active alarms for Turbidity and Level
                [
                    'device_index' => 2,
                    'alarm_code' => 'TURBIDITY_HIGH',
                    'parameter_name' => 'turbidity',
                    'alarm_message' => 'Kekeruhan air tinggi',
                    'current_value' => 32.8,
                    'threshold_value' => 25.0,
                    'severity' => 'critical',
                    'status' => 'active',
                    'started_at' => now()->subDays(5),
                    'resolved_at' => null,
                ],
                [
                    'device_index' => 2,
                    'alarm_code' => 'LEVEL_LOW',
                    'parameter_name' => 'level_percent',
                    'alarm_message' => 'Level air terlalu rendah',
                    'current_value' => 14.2,
                    'threshold_value' => 20.0,
                    'severity' => 'warning',
                    'status' => 'active',
                    'started_at' => now()->subDays(3),
                    'resolved_at' => null,
                ],
            ];

            foreach ($alarmDefinitions as $definition) {
                AlarmLog::create([
                    'device_id' => $devices[$definition['device_index']]->id,
                    'alarm_code' => $definition['alarm_code'],
                    'parameter_name' => $definition['parameter_name'],
                    'alarm_message' => $definition['alarm_message'],
                    'current_value' => $definition['current_value'],
                    'threshold_value' => $definition['threshold_value'],
                    'severity' => $definition['severity'],
                    'status' => $definition['status'],
                    'started_at' => $definition['started_at'],
                    'resolved_at' => $definition['resolved_at'],
                    'acknowledged_by' => $owner->id,
                    'acknowledged_at' => $definition['status'] === 'resolved' ? $definition['resolved_at'] : null,
                ]);
            }
        });
    }

    private function seedTelemetryLatest(Device $device, Collection $rows): void
    {
        $latest = $rows->sortByDesc('recorded_at')->first();

        if (! $latest) {
            return;
        }

        TelemetryLatest::updateOrCreate(
            ['device_id' => $device->id],
            [
                'ph' => $latest->ph,
                'turbidity' => $latest->turbidity,
                'total_pulse' => (int) $rows->sum('delta_pulse'),
                'last_delta_pulse' => $latest->delta_pulse,
                'last_usage_liter' => $latest->usage_liter,
                'total_usage_liter' => $rows->sum('usage_liter'),
                'sample_flow' => $latest->sample_flow,
                'level_distance_cm' => $latest->level_distance_cm,
                'level_percent' => $latest->level_percent,
                'level_cm' => $latest->level_cm,
                'device_voltage_mcu' => $latest->device_voltage_mcu,
                'device_voltage_5v' => $latest->device_voltage_5v,
                'device_voltage_input' => $latest->device_voltage_input,
                'signal_strength' => $latest->signal_strength,
                'status_code' => $latest->status_code,
                'alarm_code' => $latest->alarm_code,
                'valve_state' => $latest->valve_state,
                'recorded_at' => $latest->recorded_at,
                'last_seen_at' => $latest->recorded_at,
            ]
        );
    }

    private function seedTelemetryAggregates(Device $device, Collection $rows): void
    {
        $this->seedAggregateRows($device, $rows->groupBy(fn ($row) => $row->recorded_at->copy()->startOfHour()->timestamp), TelemetryHourly::class, 'hour');
        $this->seedAggregateRows($device, $rows->groupBy(fn ($row) => $row->recorded_at->copy()->startOfDay()->timestamp), TelemetryDaily::class, 'day');
        $this->seedAggregateRows($device, $rows->groupBy(fn ($row) => $row->recorded_at->copy()->startOfMonth()->timestamp), TelemetryMonthly::class, 'month');
        $this->seedAggregateRows($device, $rows->groupBy(fn ($row) => $row->recorded_at->copy()->startOfYear()->timestamp), TelemetryYearly::class, 'year');
    }

    private function seedAggregateRows(Device $device, Collection $groups, string $modelClass, string $precision): void
    {
        $groups->each(function (Collection $group) use ($device, $modelClass, $precision) {
            $ordered = $group->sortBy('recorded_at')->values();
            $first = $ordered->first();
            $last = $ordered->last();

            if (! $first || ! $last) {
                return;
            }

            $periodStart = match ($precision) {
                'hour' => $first->recorded_at->copy()->startOfHour(),
                'day' => $first->recorded_at->copy()->startOfDay(),
                'month' => $first->recorded_at->copy()->startOfMonth(),
                'year' => $first->recorded_at->copy()->startOfYear(),
                default => $first->recorded_at->copy(),
            };

            $periodEnd = match ($precision) {
                'hour' => $periodStart->copy()->endOfHour(),
                'day' => $periodStart->copy()->endOfDay(),
                'month' => $periodStart->copy()->endOfMonth(),
                'year' => $periodStart->copy()->endOfYear(),
                default => $last->recorded_at->copy(),
            };

            $modelClass::updateOrCreate(
                [
                    'device_id' => $device->id,
                    'period_start' => $periodStart,
                ],
                [
                    'period_end' => $periodEnd,
                    'avg_ph' => round($ordered->avg('ph'), 2),
                    'min_ph' => round($ordered->min('ph'), 2),
                    'max_ph' => round($ordered->max('ph'), 2),
                    'avg_turbidity' => round($ordered->avg('turbidity'), 2),
                    'min_turbidity' => round($ordered->min('turbidity'), 2),
                    'max_turbidity' => round($ordered->max('turbidity'), 2),
                    'avg_sample_flow' => round($ordered->avg('sample_flow'), 2),
                    'min_sample_flow' => round($ordered->min('sample_flow'), 2),
                    'max_sample_flow' => round($ordered->max('sample_flow'), 2),
                    'usage_liter' => round($ordered->sum('usage_liter'), 6),
                    'avg_level_percent' => round($ordered->avg('level_percent'), 2),
                    'min_level_percent' => round($ordered->min('level_percent'), 2),
                    'max_level_percent' => round($ordered->max('level_percent'), 2),
                    'avg_level_cm' => round($ordered->avg('level_cm'), 2),
                    'min_level_cm' => round($ordered->min('level_cm'), 2),
                    'max_level_cm' => round($ordered->max('level_cm'), 2),
                    'avg_device_voltage_mcu' => round($ordered->avg('device_voltage_mcu'), 2),
                    'min_device_voltage_mcu' => round($ordered->min('device_voltage_mcu'), 2),
                    'max_device_voltage_mcu' => round($ordered->max('device_voltage_mcu'), 2),
                    'avg_device_voltage_5v' => round($ordered->avg('device_voltage_5v'), 2),
                    'min_device_voltage_5v' => round($ordered->min('device_voltage_5v'), 2),
                    'max_device_voltage_5v' => round($ordered->max('device_voltage_5v'), 2),
                    'avg_device_voltage_input' => round($ordered->avg('device_voltage_input'), 2),
                    'min_device_voltage_input' => round($ordered->min('device_voltage_input'), 2),
                    'max_device_voltage_input' => round($ordered->max('device_voltage_input'), 2),
                    'avg_signal_strength' => round($ordered->avg('signal_strength'), 2),
                    'min_signal_strength' => (int) $ordered->min('signal_strength'),
                    'max_signal_strength' => (int) $ordered->max('signal_strength'),
                    'sample_count' => $ordered->count(),
                ]
            );
        });
    }

    private function qualityStatus(float $ph, float $turbidity): string
    {
        $phStatus = match (true) {
            $ph >= 6.5 && $ph <= 8.5 => 'good',
            $ph >= 6.0 && $ph <= 9.0 => 'warning',
            default => 'bad',
        };
        $turbidityStatus = match (true) {
            $turbidity <= 25 => 'good',
            $turbidity <= 50 => 'warning',
            default => 'bad',
        };

        if ($phStatus === 'bad' || $turbidityStatus === 'bad') {
            return 'bad';
        }

        if ($phStatus === 'warning' && $turbidityStatus === 'warning') {
            return 'bad';
        }

        if ($phStatus === 'warning' || $turbidityStatus === 'warning') {
            return 'warning';
        }

        return 'good';
    }
}
