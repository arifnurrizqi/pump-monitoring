<?php

namespace Tests\Feature;

use App\Events\SourceTelemetryUpdated;
use App\Models\AlarmLog;
use App\Models\Device;
use App\Models\SourceInterval;
use App\Models\User;
use App\Services\MqttTelemetryReceiver;
use App\Services\SourceLiveState;
use App\Services\SourceTelemetryBucketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MqttParameterMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_unsupported_schema_version_is_not_ingested(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        Event::fake([SourceTelemetryUpdated::class]);
        $device = Device::create(['device_sn' => 'GW-PUMP-LEGACY', 'device_tag' => 'Gateway Pompa', 'is_active' => true]);
        $source = $device->sources()->create(['source_key' => 'PM-01', 'name' => 'Power meter', 'type' => 'power_meter']);
        $source->parameters()->create(['key' => 'voltage_l1', 'label' => 'Tegangan L1', 'unit' => 'V']);

        app(MqttTelemetryReceiver::class)->receive('devices/GW-PUMP-LEGACY/telemetry', json_encode([
            'schema_version' => 1, 'device_sn' => $device->device_sn,
            'message_id' => 'legacy-1', 'recorded_at' => now()->toIso8601String(),
            'sources' => [['source' => 'PM-01', 'values' => ['voltage_l1' => 230]]],
        ], JSON_THROW_ON_ERROR));

        Event::assertNotDispatched(SourceTelemetryUpdated::class);
        $this->assertDatabaseCount('source_intervals', 0);
    }

    public function test_version_two_broadcasts_each_sample_and_saves_one_interval_per_source(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $this->travelTo(Carbon::parse('2026-10-01 10:00:05'));
        Event::fake([SourceTelemetryUpdated::class]);

        $device = Device::create(['device_sn' => 'GW-PUMP-1', 'device_tag' => 'Gateway Pompa', 'is_active' => true]);
        $power = $device->sources()->create(['source_key' => 'PM-01', 'name' => 'Power meter', 'type' => 'power_meter']);
        $power->parameters()->create(['key' => 'voltage_l1', 'label' => 'Tegangan L1', 'unit' => 'V']);
        $temperature = $device->sources()->create(['source_key' => 'TEMP-01', 'name' => 'Motor', 'type' => 'temperature']);
        $temperature->parameters()->create(['key' => 'motor_temp', 'label' => 'Temperatur motor', 'unit' => '°C']);

        $receiver = app(MqttTelemetryReceiver::class);
        foreach ([220, 230, 240] as $index => $voltage) {
            $payload = [
                'schema_version' => 2, 'device_sn' => $device->device_sn,
                'message_id' => "msg-{$index}", 'recorded_at' => now()->toIso8601String(),
                'sources' => [
                    ['source' => 'PM-01', 'values' => ['voltage_l1' => $voltage]],
                    ['source' => 'TEMP-01', 'values' => ['motor_temp' => 40 + $index]],
                ],
            ];
            $receiver->receive('devices/GW-PUMP-1/telemetry', json_encode($payload, JSON_THROW_ON_ERROR));
            if ($index === 0) {
                $receiver->receive('devices/GW-PUMP-1/telemetry', json_encode($payload, JSON_THROW_ON_ERROR));
            }
            $this->travel(5)->seconds();
        }

        Event::assertDispatchedTimes(SourceTelemetryUpdated::class, 3);
        $this->assertDatabaseCount('source_intervals', 0);
        $device->load('sources');
        $this->assertSame(240.0, app(SourceLiveState::class)->forDevice($device)[$power->id]['voltage_l1']['value']);

        $this->travel(1)->minutes();
        app(SourceTelemetryBucketService::class)->flushClosed();

        $this->assertDatabaseCount('source_intervals', 2);
        $interval = SourceInterval::where('data_source_id', $power->id)->firstOrFail();
        $this->assertSame(3, $interval->sample_count);
        $this->assertSame(220.0, $interval->metrics['voltage_l1']['min']);
        $this->assertSame(240.0, $interval->metrics['voltage_l1']['max']);
        $this->assertSame(690.0, $interval->metrics['voltage_l1']['sum']);
        $this->assertNotNull($device->fresh()->last_seen_at);
    }

    public function test_unknown_parameter_is_rejected_without_broadcast_or_history(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        Event::fake([SourceTelemetryUpdated::class]);
        $device = Device::create(['device_sn' => 'GW-PUMP-2', 'device_tag' => 'Gateway Pompa', 'is_active' => true]);
        $device->sources()->create(['source_key' => 'PM-01', 'name' => 'Power meter', 'type' => 'power_meter']);
        app(MqttTelemetryReceiver::class)->receive('devices/GW-PUMP-2/telemetry', json_encode([
            'schema_version' => 2, 'device_sn' => 'GW-PUMP-2', 'message_id' => 'bad-param',
            'recorded_at' => now()->toIso8601String(),
            'sources' => [['source' => 'PM-01', 'values' => ['unregistered' => 5]]],
        ], JSON_THROW_ON_ERROR));

        Event::assertNotDispatched(SourceTelemetryUpdated::class);
        $this->assertDatabaseCount('source_intervals', 0);
    }

    public function test_parameter_alarm_ignores_bad_quality_and_resolves_on_recovery(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        Event::fake([SourceTelemetryUpdated::class]);
        $device = Device::create(['device_sn' => 'GW-PUMP-4', 'device_tag' => 'Gateway Pompa', 'is_active' => true]);
        $source = $device->sources()->create(['source_key' => 'TEMP-01', 'name' => 'Motor', 'type' => 'temperature']);
        $parameter = $source->parameters()->create([
            'key' => 'motor_temp', 'label' => 'Temperatur motor', 'unit' => '°C',
            'alarm_max' => 80, 'alarm_enabled' => true,
        ]);
        $receiver = app(MqttTelemetryReceiver::class);
        foreach ([['error', null], ['good', 81], ['good', 82], ['good', 83]] as $index => [$quality, $value]) {
            $receiver->receive('devices/GW-PUMP-4/telemetry', json_encode([
                'schema_version' => 2, 'device_sn' => 'GW-PUMP-4', 'message_id' => "alarm-{$index}",
                'recorded_at' => now()->toIso8601String(),
                'sources' => [['source' => 'TEMP-01', 'values' => ['motor_temp' => $value],
                    'quality' => ['motor_temp' => $quality]]],
            ], JSON_THROW_ON_ERROR));
        }
        $this->assertSame('active', AlarmLog::where('source_parameter_id', $parameter->id)->firstOrFail()->status);

        $receiver->receive('devices/GW-PUMP-4/telemetry', json_encode([
            'schema_version' => 2, 'device_sn' => 'GW-PUMP-4', 'message_id' => 'recovered',
            'recorded_at' => now()->toIso8601String(),
            'sources' => [['source' => 'TEMP-01', 'values' => ['motor_temp' => 70]]],
        ], JSON_THROW_ON_ERROR));
        $this->assertSame('resolved', AlarmLog::where('source_parameter_id', $parameter->id)->firstOrFail()->status);
    }

    public function test_power_meter_template_uses_asset_phase_count_and_viewer_cannot_edit(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $device = Device::create(['device_sn' => 'GW-PUMP-3', 'device_tag' => 'Gateway Pompa', 'is_active' => true]);
        $device->users()->attach($owner->id, ['role' => 'owner']);
        $device->users()->attach($viewer->id, ['role' => 'viewer']);

        $this->actingAs($owner)->post(route('devices.monitoring.site', $device), [
            'code' => 'SUMUR-A', 'name' => 'Sumur A',
        ])->assertRedirect();
        $this->post(route('devices.monitoring.asset', $device), [
            'name' => 'Pompa A', 'type' => 'pump', 'phase_count' => 3,
        ])->assertRedirect();
        $asset = $device->fresh()->site->assets()->firstOrFail();
        $this->post(route('devices.monitoring.source', $device), [
            'source_key' => 'PM-01', 'name' => 'Power Meter A', 'type' => 'power_meter',
            'monitored_asset_id' => $asset->id,
        ])->assertRedirect();
        $source = $device->sources()->firstOrFail();
        $this->assertSame(10, $source->parameters()->count());
        $this->assertTrue($source->parameters()->where('key', 'voltage_l3')->exists());

        $this->actingAs($viewer)->post(route('devices.monitoring.parameter', [$device, $source]), [
            'key' => 'motor_temp', 'label' => 'Temperatur',
        ])->assertForbidden();
    }
}
