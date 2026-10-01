<?php

namespace Tests\Feature;

use App\Jobs\PublishValveCommand;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\TelemetryLatest;
use App\Models\User;
use App\Services\MqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ValveCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_queue_a_valve_command_and_poll_its_status(): void
    {
        Queue::fake();
        [$user, $device] = $this->operatorAndDevice();

        TelemetryLatest::create(['device_id' => $device->id, 'valve_state' => false]);

        $response = $this->actingAs($user)->postJson(route('devices.valve.store', $device), [
            'value' => true,
        ])->assertAccepted()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.requested_value', true)
            ->assertJsonPath('data.actual_value', false);

        $command = DeviceCommand::findOrFail($response->json('data.id'));
        $this->assertNull($command->expires_at);
        Queue::assertPushed(PublishValveCommand::class, fn ($job) => $job->commandId === $command->id);

        $this->actingAs($user)
            ->getJson(route('devices.valve.commands.show', [$device, $command]))
            ->assertOk()
            ->assertJsonPath('data.status', 'queued')
            ->assertJsonPath('data.actual_value', false);
    }

    public function test_telemetry_acknowledges_only_the_matching_non_expired_command(): void
    {
        [, $device] = $this->operatorAndDevice();
        $device->setting()->create(['alarm_enabled' => false]);

        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'valve',
            'command_key' => 'valve_state',
            'command_value' => '1',
            'status' => 'sent',
        ]);

        $this->postJson('/api/v1/telemetry', [
            'device_sn' => $device->device_sn,
            'valve_state' => true,
            'command_id' => $command->id,
        ])->assertCreated();

        $this->assertSame('ack', $command->fresh()->status);
        $this->assertNotNull($command->fresh()->ack_at);
    }

    public function test_publish_job_uses_qos_one_without_retaining_the_command(): void
    {
        [, $device] = $this->operatorAndDevice();
        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'valve',
            'command_key' => 'valve_state',
            'command_value' => '1',
            'status' => 'queued',
            'expires_at' => now()->addSeconds(15),
        ]);

        $mqtt = Mockery::mock(MqttService::class);
        $mqtt->shouldReceive('connect')->once();
        $mqtt->shouldReceive('publish')->once()->with(
            "devices/{$device->device_sn}/commands/drain-valve",
            Mockery::on(fn ($payload) => json_decode($payload, true)['command_id'] === $command->id),
            1,
            false,
        );
        $mqtt->shouldReceive('disconnect')->once();

        (new PublishValveCommand($command->id))->handle($mqtt);

        $this->assertSame('sent', $command->fresh()->status);
        $this->assertSame(1, $command->fresh()->attempts);
        $this->assertNotNull($command->fresh()->expires_at);
    }

    /** @return array{User, Device} */
    private function operatorAndDevice(): array
    {
        $user = User::factory()->create();
        $device = Device::create([
            'device_sn' => 'VALVE-'.fake()->unique()->numerify('####'),
            'device_tag' => 'Drain Valve',
            'is_active' => true,
        ]);
        $device->users()->attach($user->id, ['role' => 'operator']);

        return [$user, $device];
    }
}
