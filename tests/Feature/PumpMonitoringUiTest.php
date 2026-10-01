<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumpMonitoringUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_new_gateway_opens_source_setup_and_interval_can_be_changed(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('devices.store'), [
            'device_sn' => 'GW-NEW-PUMP', 'device_tag' => 'Pompa Baru', 'is_active' => 1,
        ]);

        $device = Device::where('device_sn', 'GW-NEW-PUMP')->firstOrFail();
        $response->assertRedirect(route('devices.monitoring.index', $device));
        $this->get(route('devices.monitoring.index', $device))
            ->assertOk()->assertSee('Interval kirim gateway');
        $this->patch(route('devices.monitoring.gateway', $device), [
            'send_interval_sec' => 30,
        ])->assertRedirect();
        $this->assertSame(30, $device->setting->fresh()->send_interval_sec);
    }

    public function test_dashboard_and_detail_show_registered_pump_parameters(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $user = User::factory()->create();
        $device = Device::create(['device_sn' => 'GW-PUMP-UI', 'device_tag' => 'Pompa Sumur A', 'is_active' => true]);
        $device->users()->attach($user->id, ['role' => 'owner']);
        $source = $device->sources()->create(['source_key' => 'PM-01', 'name' => 'Power Meter A', 'type' => 'power_meter']);
        $source->parameters()->create(['key' => 'voltage_l1', 'label' => 'Tegangan L1', 'unit' => 'V']);
        $source->intervals()->create([
            'bucket_start' => now()->subMinute()->startOfMinute(),
            'last_recorded_at' => now()->subMinute(),
            'sample_count' => 2,
            'metrics' => ['voltage_l1' => [
                'sum' => 460, 'count' => 2, 'min' => 228, 'max' => 232,
                'last' => 232, 'quality' => 'good', 'recorded_at' => now()->subMinute()->toIso8601String(),
            ]],
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('Pompa Sumur A')->assertSee('Power Meter A')->assertSee('Tegangan L1');
        $this->get(route('devices.show', $device))
            ->assertOk()->assertSee('Tren parameter')->assertSee('230,00');
        $this->get(route('datalogger.index'))
            ->assertOk()->assertSee('Power Meter A')->assertSee('Tegangan L1');
    }

    public function test_other_users_cannot_view_gateway_detail_or_history(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $device = Device::create(['device_sn' => 'GW-PRIVATE', 'device_tag' => 'Pompa Privat', 'is_active' => true]);
        $device->users()->attach($owner->id, ['role' => 'owner']);
        $source = $device->sources()->create(['source_key' => 'PM-01', 'name' => 'Meter Privat', 'type' => 'power_meter']);
        $source->intervals()->create([
            'bucket_start' => now()->subMinute()->startOfMinute(),
            'last_recorded_at' => now()->subMinute(),
            'sample_count' => 1,
            'metrics' => ['voltage_l1' => ['sum' => 230, 'count' => 1, 'min' => 230, 'max' => 230, 'last' => 230, 'quality' => 'good']],
        ]);

        $this->actingAs($other)->get(route('devices.show', $device))->assertForbidden();
        $this->get(route('datalogger.index', ['device_id' => $device->id]))
            ->assertOk()->assertDontSee('Meter Privat');
    }

    public function test_default_demo_seed_populates_pump_sources_and_history(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('source_intervals', 16314);
        $threePhase = Device::where('device_sn', 'DEMO-PUMP-001')->firstOrFail();
        $singlePhase = Device::where('device_sn', 'DEMO-PUMP-002')->firstOrFail();
        $booster = Device::where('device_sn', 'DEMO-PUMP-003')->firstOrFail();
        $this->assertTrue($threePhase->sources()->where('source_key', 'PM-01')
            ->firstOrFail()->parameters()->where('key', 'voltage_l3')->exists());
        $this->assertSame('tegangan', $threePhase->sources()->where('source_key', 'PM-01')
            ->firstOrFail()->parameters()->where('key', 'voltage_l3')->firstOrFail()->chart_group);
        $this->assertFalse($singlePhase->sources()->where('source_key', 'PM-01')
            ->firstOrFail()->parameters()->where('key', 'voltage_l2')->exists());

        $southMotor = $singlePhase->sources()->where('source_key', 'MOTOR-01')->firstOrFail();
        $old = $southMotor->intervals()->oldest('bucket_start')->firstOrFail()->metrics;
        $recent = $southMotor->intervals()->latest('bucket_start')->firstOrFail()->metrics;
        $this->assertGreaterThan(80, $recent['motor_temp']['last']);
        $this->assertGreaterThan($recent['flow_rate']['last'] + 5, $old['flow_rate']['last']);

        $boosterMotor = $booster->sources()->where('source_key', 'MOTOR-01')->firstOrFail();
        $lastHour = $boosterMotor->intervals()->where('bucket_start', '>=', now()->utc()->subHour())
            ->get()->map(fn ($interval) => $interval->metrics['flow_rate']['last'])->all();
        $this->assertEquals(0, min($lastHour));
        $this->assertGreaterThan(20, max($lastHour));

        $threePhase->load('sources.parameters');
        $charts = app(\App\Services\DeviceChartService::class);
        foreach (['1h', '12h', '1d', '7d', '1mo'] as $range) {
            $groups = $charts->history($threePhase, $range);
            $this->assertSame('tegangan', $groups[0]['key']);
            $this->assertCount(3, $groups[0]['series']);
            $this->assertGreaterThan(25, count($groups[0]['points']));
        }
    }

    public function test_device_charts_group_three_phase_voltage_and_filter_history_by_range(): void
    {
        config()->set('mqtt.live_cache_store', 'array');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $device = Device::create(['device_sn' => 'GW-CHART', 'device_tag' => 'Pompa Grafik', 'is_active' => true]);
        $device->users()->attach($owner->id, ['role' => 'owner']);
        $source = $device->sources()->create(['source_key' => 'PM-01', 'name' => 'Power Meter', 'type' => 'power_meter']);
        foreach (range(1, 3) as $phase) {
            $source->parameters()->create([
                'key' => "voltage_l{$phase}", 'label' => "Tegangan L{$phase}",
                'unit' => 'V', 'chart_group' => 'tegangan',
            ]);
        }
        foreach ([20, 120, 60 * 24 * 20] as $minutesAgo) {
            $recordedAt = now()->subMinutes($minutesAgo);
            $source->intervals()->create([
                'bucket_start' => $recordedAt->copy()->startOfMinute(),
                'last_recorded_at' => $recordedAt,
                'sample_count' => 2,
                'metrics' => [
                    'voltage_l1' => ['sum' => 460, 'count' => 2, 'recorded_at' => $recordedAt->toIso8601String()],
                    'voltage_l2' => ['sum' => 462, 'count' => 2, 'recorded_at' => $recordedAt->toIso8601String()],
                    'voltage_l3' => ['sum' => 458, 'count' => 2, 'recorded_at' => $recordedAt->toIso8601String()],
                ],
            ]);
        }

        $this->actingAs($owner)->get(route('devices.show', $device))
            ->assertOk()->assertSee('Tegangan')->assertSee('data-chart-range="1h"', false);
        $hour = $this->getJson(route('devices.charts', $device));
        $hour->assertOk()->assertJsonPath('range', '1h')->assertJsonPath('groups.0.key', 'tegangan');
        $this->assertCount(3, $hour->json('groups.0.series'));
        $this->assertCount(1, $hour->json('groups.0.points'));
        $this->assertEquals(230, $hour->json('groups.0.points.0.values.'.$source->id.':voltage_l1'));

        $twelveHours = $this->getJson(route('devices.charts', ['device' => $device, 'range' => '12h']));
        $this->assertCount(2, $twelveHours->json('groups.0.points'));
        $month = $this->getJson(route('devices.charts', ['device' => $device, 'range' => '1mo']));
        $this->assertGreaterThanOrEqual(2, count($month->json('groups.0.points')));
        $this->getJson(route('devices.charts', ['device' => $device, 'range' => 'invalid']))->assertUnprocessable();

        $this->actingAs($stranger)->getJson(route('devices.charts', $device))->assertForbidden();
    }

    public function test_operator_can_choose_a_custom_parameter_chart_group(): void
    {
        $owner = User::factory()->create();
        $device = Device::create(['device_sn' => 'GW-VIBRATION', 'device_tag' => 'Pompa Getaran', 'is_active' => true]);
        $device->users()->attach($owner->id, ['role' => 'owner']);
        $source = $device->sources()->create(['source_key' => 'MOTOR-01', 'name' => 'Motor', 'type' => 'other']);

        $this->actingAs($owner)->post(route('devices.monitoring.parameter', [$device, $source]), [
            'key' => 'vibration', 'label' => 'Getaran', 'unit' => 'mm/s', 'chart_group' => 'getaran',
        ])->assertRedirect();
        $parameter = $source->parameters()->firstOrFail();
        $this->assertSame('getaran', $parameter->chart_group);
        $this->get(route('devices.show', $device))->assertOk()->assertSee('Getaran');

        $this->patch(route('devices.monitoring.parameter.update', [$device, $source, $parameter]), [
            'label' => 'Getaran', 'unit' => 'mm/s', 'chart_group' => '',
        ])->assertRedirect();
        $this->assertNull($parameter->fresh()->chart_group);
    }
}
