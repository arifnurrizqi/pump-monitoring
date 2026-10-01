<?php

namespace Tests\Feature;

use App\Events\TelemetryUpdated;
use App\Models\AlarmLog;
use App\Models\Device;
use App\Models\TelemetryLatest;
use App\Models\TelemetryRaw;
use App\Services\MqttTelemetryReceiver;
use App\Services\LiveTelemetryState;
use App\Services\TelemetryBucketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MqttTelemetryReceptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mqtt_message_uses_existing_ingestion_and_duplicate_does_not_increment_totals(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $this->travelTo(Carbon::parse('2026-10-01 10:00:05'));
        Event::fake([TelemetryUpdated::class]);
        $device = Device::create([
            'device_sn' => 'GW-001',
            'device_tag' => 'Gateway sumur A',
            'is_active' => true,
        ]);
        $device->setting()->create(['pulse_per_liter' => 450, 'alarm_enabled' => false]);

        $message = json_encode([
            'device_sn' => 'GW-001',
            'message_id' => 'GW-001-1',
            'delta_pulse' => 10,
            'ph' => 7.2,
        ], JSON_THROW_ON_ERROR);

        $receiver = app(MqttTelemetryReceiver::class);
        $receiver->receive('devices/GW-001/telemetry', $message);
        $receiver->receive('devices/GW-001/telemetry', $message);

        $this->assertDatabaseCount('telemetry_raw', 0);
        Event::assertDispatchedTimes(TelemetryUpdated::class, 1);

        $this->travelTo(Carbon::parse('2026-10-01 10:01:05'));
        app(TelemetryBucketService::class)->flushClosedBuckets();
        $this->assertDatabaseCount('telemetry_raw', 1);
        $this->assertSame(10, TelemetryLatest::where('device_id', $device->id)->firstOrFail()->total_pulse);
        $this->assertSame(1, TelemetryRaw::firstOrFail()->sample_count);
    }

    public function test_mqtt_rejects_retained_and_mismatched_device_messages(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $receiver = app(MqttTelemetryReceiver::class);
        $message = json_encode(['device_sn' => 'GW-002', 'message_id' => 'one'], JSON_THROW_ON_ERROR);

        $receiver->receive('devices/GW-001/telemetry', $message);
        $receiver->receive('devices/GW-002/telemetry', $message, true);

        $this->assertDatabaseCount('telemetry_raw', 0);
    }

    public function test_many_live_messages_produce_one_interval_with_minimum_and_maximum(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
        Event::fake([TelemetryUpdated::class]);

        $device = Device::create([
            'device_sn' => 'GW-003', 'device_tag' => 'Gateway sumur C', 'is_active' => true,
        ]);
        $device->setting()->create(['pulse_per_liter' => 10, 'alarm_enabled' => false]);
        $receiver = app(MqttTelemetryReceiver::class);

        foreach ([7.0, 8.0, 9.0] as $index => $ph) {
            $receiver->receive('devices/GW-003/telemetry', json_encode([
                'device_sn' => 'GW-003',
                'message_id' => "GW-003-{$index}",
                'ph' => $ph,
                'delta_pulse' => 10,
            ], JSON_THROW_ON_ERROR));
            $this->travel(5)->seconds();
        }

        Event::assertDispatchedTimes(TelemetryUpdated::class, 3);
        $this->assertDatabaseCount('telemetry_raw', 0);
        app(LiveTelemetryState::class)->apply($device);
        $this->assertEquals(9, (float) $device->latestData->ph);
        $this->assertSame(3.0, $device->usage_today);

        $this->travel(1)->minutes();
        app(TelemetryBucketService::class)->flushClosedBuckets();

        $raw = TelemetryRaw::firstOrFail();
        $this->assertSame(3, $raw->sample_count);
        $this->assertEquals(3, (float) $raw->usage_liter);
        $this->assertEquals(8, (float) $raw->ph);
        $this->assertEquals(7, $raw->raw_payload['metrics']['ph']['min']);
        $this->assertEquals(9, $raw->raw_payload['metrics']['ph']['max']);
        $this->assertSame(30, TelemetryLatest::where('device_id', $device->id)->firstOrFail()->total_pulse);
    }

    public function test_live_alarm_is_checked_before_bucket_is_saved(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
        Event::fake([TelemetryUpdated::class]);
        $device = Device::create(['device_sn' => 'GW-004', 'device_tag' => 'Gateway D', 'is_active' => true]);
        $device->setting()->create(['ph_min' => 6.5, 'ph_max' => 8.5, 'alarm_enabled' => true]);
        $receiver = app(MqttTelemetryReceiver::class);

        foreach ([9.0, 9.1, 9.2] as $index => $ph) {
            $receiver->receive('devices/GW-004/telemetry', json_encode([
                'device_sn' => 'GW-004', 'message_id' => "alarm-{$index}", 'ph' => $ph,
            ], JSON_THROW_ON_ERROR));
        }

        $this->assertDatabaseCount('telemetry_raw', 0);
        $this->assertSame('active', AlarmLog::firstOrFail()->status);
        $receiver->receive('devices/GW-004/telemetry', json_encode([
            'device_sn' => 'GW-004', 'message_id' => 'alarm-recovery', 'ph' => 7.0,
        ], JSON_THROW_ON_ERROR));
        $this->assertSame('resolved', AlarmLog::firstOrFail()->status);
    }

    public function test_late_message_is_kept_without_replacing_newer_live_value(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $this->travelTo(Carbon::parse('2026-10-01 10:00:20'));
        Event::fake([TelemetryUpdated::class]);
        $device = Device::create(['device_sn' => 'GW-005', 'device_tag' => 'Gateway E', 'is_active' => true]);
        $device->setting()->create(['pulse_per_liter' => 10, 'alarm_enabled' => false]);
        $receiver = app(MqttTelemetryReceiver::class);

        foreach ([['new', '10:00:15', 8], ['late', '10:00:05', 7]] as [$id, $time, $ph]) {
            $receiver->receive('devices/GW-005/telemetry', json_encode([
                'device_sn' => 'GW-005', 'message_id' => $id,
                'recorded_at' => "2026-10-01T{$time}+07:00",
                'ph' => $ph, 'delta_pulse' => 10,
            ], JSON_THROW_ON_ERROR));
        }

        $this->assertDatabaseCount('telemetry_raw', 1);
        $this->assertEquals(7, (float) TelemetryRaw::firstOrFail()->ph);
        Event::assertDispatchedTimes(TelemetryUpdated::class, 1);

        $this->travelTo(Carbon::parse('2026-10-01 10:01:20'));
        app(TelemetryBucketService::class)->flushClosedBuckets();
        $this->assertSame(20, TelemetryLatest::where('device_id', $device->id)->firstOrFail()->total_pulse);
    }
}
