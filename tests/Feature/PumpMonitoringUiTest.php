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
        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('source_intervals', 360);
        $threePhase = Device::where('device_sn', 'DEMO-PUMP-001')->firstOrFail();
        $singlePhase = Device::where('device_sn', 'DEMO-PUMP-002')->firstOrFail();
        $this->assertTrue($threePhase->sources()->where('source_key', 'PM-01')
            ->firstOrFail()->parameters()->where('key', 'voltage_l3')->exists());
        $this->assertFalse($singlePhase->sources()->where('source_key', 'PM-01')
            ->firstOrFail()->parameters()->where('key', 'voltage_l2')->exists());
    }
}
