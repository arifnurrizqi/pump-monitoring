<?php

namespace Tests\Feature;

use App\Broadcasting\DeviceChannel;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealtimeChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_only_subscribe_to_an_accessible_device_channel(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $device = Device::create([
            'device_sn' => 'WS-DEVICE-001',
            'device_tag' => 'Realtime Device',
            'is_active' => true,
        ]);
        $user->devices()->attach($device, ['role' => 'viewer']);
        $this->assertTrue($user->devices()->whereKey($device->id)->exists());

        $channel = app(DeviceChannel::class);

        $this->assertTrue($channel->join($user, $device->id));
        $this->assertFalse($channel->join($otherUser, $device->id));
    }
}
