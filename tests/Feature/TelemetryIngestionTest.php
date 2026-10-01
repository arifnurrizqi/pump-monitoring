<?php

namespace Tests\Feature;

use App\Events\TelemetryUpdated;
use App\Models\AlarmLog;
use App\Models\Device;
use App\Models\TelemetryLatest;
use App\Models\TelemetryRaw;
use App\Services\ParameterHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TelemetryIngestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_telemetry_and_calculates_level_and_pulse_totals(): void
    {
        Event::fake([TelemetryUpdated::class]);

        $device = $this->createDevice();
        $device->setting()->create([
            'tank_height_cm' => 200,
            'pulse_per_liter' => 450,
            'sample_flow_min' => 0.5,
            'sample_flow_max' => 1.0,
            'alarm_enabled' => false,
        ]);

        $firstResponse = $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 7.2,
            'sample_flow' => 0.75,
            'level_distance_cm' => 50,
            'delta_pulse' => 12,
        ])->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.level_distance_cm', 50)
            ->assertJsonPath('data.water_height_cm', 150)
            ->assertJsonPath('data.level_percent', 75);

        $this->assertEqualsWithDelta(
            12 / 450,
            (float) $firstResponse->json('data.usage_month_liter'),
            0.000001,
        );

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'level_distance_cm' => 100,
            'delta_pulse' => 8,
            'sample_flow' => 0.9,
        ])->assertCreated()
            ->assertJsonPath('data.usage_month_liter', fn ($usage) => abs((float) $usage - (20 / 450)) < 0.000001);

        $this->assertDatabaseCount('telemetry_raw', 2);
        $this->assertEquals(50.0, (float) TelemetryRaw::first()->level_distance_cm);
        $this->assertEquals(150.0, (float) TelemetryRaw::first()->level_cm);
        $this->assertEquals(75.0, (float) TelemetryRaw::first()->level_percent);
        $this->assertEquals(0.75, (float) TelemetryRaw::first()->sample_flow);
        $this->assertEqualsWithDelta(12 / 450, (float) TelemetryRaw::first()->usage_liter, 0.000001);
        $this->assertEquals(450.0, (float) TelemetryRaw::first()->pulse_per_liter_used);

        $latest = TelemetryLatest::whereBelongsTo($device)->firstOrFail();
        $this->assertSame(20, $latest->total_pulse);
        $this->assertSame(8, $latest->last_delta_pulse);
        $this->assertEqualsWithDelta(20 / 450, (float) $latest->total_usage_liter, 0.000001);
        $this->assertEquals(100.0, (float) $latest->level_distance_cm);
        $this->assertEquals(100.0, (float) $latest->level_cm);
        $this->assertEquals(50.0, (float) $latest->level_percent);
        $this->assertSame(
            'good',
            app(ParameterHealthService::class)->evaluate($latest)['sample_flow']
        );
        $this->assertNotNull($device->fresh()->last_seen_at);
        Event::assertDispatched(TelemetryUpdated::class, function (TelemetryUpdated $event) use ($device): bool {
            return $event->deviceId === $device->id
                && $event->snapshot['telemetry']['total_pulse'] === 20
                && $event->snapshot['telemetry']['usage_today_liter'] > 0;
        });
    }

    public function test_quality_status_is_calculated_by_backend_and_partial_payload_preserves_sensor_state(): void
    {
        $device = $this->createDevice();
        $device->setting()->create([
            'ph_min' => 6.5,
            'ph_max' => 8.5,
            'turbidity_max' => 5,
            'sample_flow_min' => 0.5,
            'sample_flow_max' => 1,
            'alarm_enabled' => false,
        ]);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 7,
            'turbidity' => 2,
            'sample_flow' => 99,
        ])->assertCreated()
            ->assertJsonPath('data.quality_status', 'good')
            ->assertJsonPath('data.parameter_health.sample_flow', 'bad');

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'valve_state' => true,
        ])->assertCreated()
            ->assertJsonPath('data.quality_status', 'good');

        $latest = TelemetryLatest::whereBelongsTo($device)->firstOrFail();
        $this->assertEquals(7, (float) $latest->ph);
        $this->assertEquals(2, (float) $latest->turbidity);
        $this->assertSame('good', $latest->status_code);
    }

    public function test_two_quality_warnings_are_promoted_to_bad(): void
    {
        $device = $this->createDevice();
        $device->setting()->create([
            'ph_min' => 6.5,
            'ph_max' => 8.5,
            'turbidity_max' => 5,
            'alarm_enabled' => false,
        ]);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 6.25,
            'turbidity' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.parameter_health.ph', 'warning')
            ->assertJsonPath('data.parameter_health.turbidity', 'warning')
            ->assertJsonPath('data.quality_status', 'bad');

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 7,
            'turbidity' => 10,
        ])->assertCreated()
            ->assertJsonPath('data.quality_status', 'warning');

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 9.5,
        ])->assertCreated()
            ->assertJsonPath('data.quality_status', 'bad');
    }

    public function test_it_creates_default_settings_for_a_registered_device(): void
    {
        $device = $this->createDevice();

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'level_distance_cm' => 10,
        ])->assertCreated();

        $this->assertDatabaseHas('device_settings', [
            'device_id' => $device->id,
            'pulse_per_liter' => 450,
        ]);
    }

    public function test_it_rejects_an_unknown_device(): void
    {
        $this->postJson('/api/v1/telemetry', [
            'device_sn' => 'UNKNOWN',
        ])->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_it_rejects_legacy_flow_fields(): void
    {
        $device = $this->createDevice();

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'flow' => 1.2,
            'flow_total' => 10,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['flow', 'flow_total']);
    }

    public function test_legacy_level_cm_is_accepted_as_sensor_distance_but_cannot_be_duplicated(): void
    {
        $device = $this->createDevice();
        $device->setting()->create([
            'tank_height_cm' => 200,
            'pulse_per_liter' => 450,
            'alarm_enabled' => false,
        ]);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'level_cm' => 20,
        ])->assertCreated()
            ->assertJsonPath('data.level_distance_cm', 20)
            ->assertJsonPath('data.water_height_cm', 180)
            ->assertJsonPath('data.level_percent', 90);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'level_cm' => 20,
            'level_distance_cm' => 20,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['level_cm', 'level_distance_cm']);
    }

    public function test_alarm_requires_three_consecutive_anomalies_and_resolves_on_normal_data(): void
    {
        $device = $this->createDevice();
        $device->setting()->create([
            'pulse_per_liter' => 450,
            'ph_min' => 6.5,
            'ph_max' => 8.5,
            'alarm_enabled' => true,
        ]);

        foreach ([9.0, 9.1] as $ph) {
            $this->postJson('/api/v1/telemetry', [
                'device_sn' => $device->device_sn,
                'ph' => $ph,
            ])->assertCreated();
        }

        $this->assertDatabaseCount('alarm_logs', 0);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 9.2,
        ])->assertCreated();

        $alarm = AlarmLog::firstOrFail();
        $this->assertSame('ph_high', $alarm->alarm_code);
        $this->assertSame('active', $alarm->status);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'ph' => 7.0,
        ])->assertCreated();

        $this->assertSame('resolved', $alarm->fresh()->status);
    }

    private function createDevice(): Device
    {
        return Device::create([
            'device_sn' => 'WATER-001',
            'device_tag' => 'Tank Utama',
            'is_active' => true,
        ]);
    }
}
