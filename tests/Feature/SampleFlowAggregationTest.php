<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryHourly;
use App\Models\TelemetryRaw;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SampleFlowAggregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_hourly_aggregation_uses_explicit_sample_flow_columns(): void
    {
        $device = Device::create([
            'device_sn' => 'FLOW-001',
            'device_tag' => 'Sample Chamber',
            'is_active' => true,
        ]);

        foreach ([0.5, 1.0, 1.5] as $index => $sampleFlow) {
            TelemetryRaw::create([
                'device_id' => $device->id,
                'sample_flow' => $sampleFlow,
                'usage_liter' => $index + 1,
                'recorded_at' => now()->subHour()->startOfHour()->addMinutes(10),
            ]);
        }

        $this->artisan('aggregate:hourly')->assertSuccessful();

        $aggregate = TelemetryHourly::whereBelongsTo($device)->firstOrFail();
        $this->assertEquals(1.0, (float) $aggregate->avg_sample_flow);
        $this->assertEquals(0.5, (float) $aggregate->min_sample_flow);
        $this->assertEquals(1.5, (float) $aggregate->max_sample_flow);
        $this->assertEquals(6.0, (float) $aggregate->usage_liter);
    }

    public function test_device_usage_is_derived_from_delta_pulse(): void
    {
        $device = Device::create([
            'device_sn' => 'USAGE-001',
            'device_tag' => 'Usage Meter',
            'is_active' => true,
        ]);
        $device->setting()->create([
            'pulse_per_liter' => 450,
            'alarm_enabled' => false,
        ]);

        TelemetryRaw::create([
            'device_id' => $device->id,
            'delta_pulse' => 900,
            'pulse_per_liter_used' => 450,
            'usage_liter' => 2,
            'recorded_at' => now(),
        ]);

        $this->assertSame(2.0, $device->usage_today);
    }

    public function test_hourly_aggregation_uses_interval_sample_counts_and_extremes(): void
    {
        $device = Device::create([
            'device_sn' => 'FLOW-INTERVAL',
            'device_tag' => 'Interval flow',
            'is_active' => true,
        ]);
        TelemetryRaw::create([
            'device_id' => $device->id,
            'sample_flow' => 2,
            'sample_count' => 3,
            'usage_liter' => 3,
            'raw_payload' => ['metrics' => [
                'sample_flow' => ['sum' => 6, 'count' => 3, 'min' => 1, 'max' => 4],
            ]],
            'recorded_at' => now()->subHour()->startOfHour()->addMinutes(10),
        ]);
        TelemetryRaw::create([
            'device_id' => $device->id,
            'sample_flow' => 6,
            'usage_liter' => 1,
            'recorded_at' => now()->subHour()->startOfHour()->addMinutes(20),
        ]);

        $this->artisan('aggregate:hourly')->assertSuccessful();

        $hourly = TelemetryHourly::whereBelongsTo($device)->firstOrFail();
        $this->assertEquals(3, (float) $hourly->avg_sample_flow);
        $this->assertEquals(1, (float) $hourly->min_sample_flow);
        $this->assertEquals(6, (float) $hourly->max_sample_flow);
        $this->assertSame(4, $hourly->sample_count);
        $this->assertEquals(4, (float) $hourly->usage_liter);
    }

    public function test_all_aggregation_processes_the_entire_database_in_order(): void
    {
        $device = Device::create([
            'device_sn' => 'ALL-001',
            'device_tag' => 'Full Aggregation',
            'is_active' => true,
        ]);

        foreach ([
            now()->subYear()->startOfYear()->addHour(),
            now()->subMonth()->startOfMonth()->addHour(),
            now()->startOfHour(),
        ] as $recordedAt) {
            TelemetryRaw::create([
                'device_id' => $device->id,
                'sample_flow' => 1.25,
                'usage_liter' => 2.5,
                'recorded_at' => $recordedAt,
            ]);
        }

        $this->artisan('aggregate:all')
            ->expectsOutputToContain('Full database aggregation completed successfully.')
            ->assertSuccessful();

        $this->assertSame(3, DB::table('telemetry_hourly')->count());
        $this->assertSame(3, DB::table('telemetry_daily')->count());
        $this->assertSame(3, DB::table('telemetry_monthly')->count());
        $this->assertSame(2, DB::table('telemetry_yearly')->count());
        $this->assertEquals(7.5, (float) DB::table('telemetry_yearly')->sum('usage_liter'));

        $this->artisan('aggregate:all')->assertSuccessful();

        $this->assertSame(3, DB::table('telemetry_hourly')->count());
        $this->assertSame(3, DB::table('telemetry_daily')->count());
        $this->assertSame(3, DB::table('telemetry_monthly')->count());
        $this->assertSame(2, DB::table('telemetry_yearly')->count());
    }
}
