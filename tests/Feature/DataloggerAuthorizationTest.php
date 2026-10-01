<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\TelemetryRaw;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataloggerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_sees_telemetry_from_accessible_devices(): void
    {
        $user = User::factory()->create();
        $ownDevice = $this->createDevice('OWN-001', 'Device Milik Saya');
        $otherDevice = $this->createDevice('OTHER-001', 'Device Rahasia');
        $ownDevice->users()->attach($user->id, ['role' => 'viewer']);

        foreach ([$ownDevice, $otherDevice] as $device) {
            TelemetryRaw::create([
                'device_id' => $device->id,
                'ph' => 7,
                'recorded_at' => now(),
            ]);
        }

        $this->actingAs($user)
            ->get('/datalogger')
            ->assertOk()
            ->assertSee('Device Milik Saya')
            ->assertDontSee('Device Rahasia');
    }

    private function createDevice(string $serialNumber, string $tag): Device
    {
        return Device::create([
            'device_sn' => $serialNumber,
            'device_tag' => $tag,
            'is_active' => true,
        ]);
    }
}
