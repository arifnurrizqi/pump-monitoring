<?php

namespace Tests\Feature;

use App\Models\MqttSetting;
use App\Models\User;
use App\Services\MqttConfigurationService;
use App\Services\MqttService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SystemMqttSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_system_admin_can_open_and_update_mqtt_settings(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('settings.mqtt.edit'))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('settings.mqtt.update'), $this->payload())
            ->assertForbidden();
    }

    public function test_admin_can_store_encrypted_mqtt_configuration(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('settings.mqtt.edit'))
            ->assertOk()
            ->assertSee('MQTT Broker')
            ->assertDontSee('Internal / Docker')
            ->assertSee('Test Connection');

        $this->actingAs($admin)
            ->put(route('settings.mqtt.update'), $this->payload([
                'host' => 'broker.client.test',
                'port' => 8883,
                'password' => 'mqtt-secret-password',
                'mode' => 'internal',
                'tls_enabled' => '1',
            ]))
            ->assertRedirect(route('settings.mqtt.edit'));

        $setting = MqttSetting::query()->firstOrFail();
        $this->assertTrue($setting->is_active);
        $this->assertSame('broker.client.test', $setting->host);
        $this->assertSame('external', $setting->mode);
        $this->assertSame(8883, $setting->port);
        $this->assertSame('mqtt-secret-password', $setting->password);
        $this->assertSame($admin->id, $setting->updated_by);
        $this->assertNotSame('mqtt-secret-password', DB::table('mqtt_settings')->value('password'));
    }

    public function test_blank_password_keeps_the_existing_encrypted_password(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        MqttSetting::query()->create(array_merge($this->settingValues(), [
            'password' => 'existing-secret',
            'updated_by' => $admin->id,
        ]));

        $this->actingAs($admin)
            ->put(route('settings.mqtt.update'), $this->payload([
                'password' => '',
                'host' => 'new-broker.client.test',
            ]))
            ->assertRedirect(route('settings.mqtt.edit'));

        $setting = MqttSetting::query()->firstOrFail();
        $this->assertSame('new-broker.client.test', $setting->host);
        $this->assertSame('existing-secret', $setting->password);
    }

    public function test_configuration_resolver_uses_database_only_when_web_setting_is_active(): void
    {
        config()->set([
            'mqtt.host' => 'mqtt-env',
            'mqtt.port' => 1883,
        ]);

        $setting = MqttSetting::query()->create($this->settingValues([
            'is_active' => false,
            'host' => 'mqtt-database',
            'port' => 8883,
        ]));

        $resolver = app(MqttConfigurationService::class);
        $this->assertSame('environment', $resolver->resolve()['source']);
        $this->assertSame('mqtt-env', $resolver->resolve()['host']);

        $setting->update(['is_active' => true]);

        $this->assertSame('database', $resolver->resolve()['source']);
        $this->assertSame('mqtt-database', $resolver->resolve()['host']);
        $this->assertSame(8883, $resolver->resolve()['port']);
    }

    public function test_admin_can_test_submitted_configuration_without_saving_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $mqtt = Mockery::mock(MqttService::class);
        $mqtt->shouldReceive('testConnection')
            ->once()
            ->with(Mockery::on(fn (array $configuration) => $configuration['host'] === 'test-broker.client.test'))
            ->andReturn(42);
        app()->instance(MqttService::class, $mqtt);

        $this->actingAs($admin)
            ->put(route('settings.mqtt.update'), $this->payload([
                'intent' => 'test',
                'host' => 'test-broker.client.test',
                'password' => 'temporary-secret',
            ]))
            ->assertRedirect()
            ->assertSessionHas('mqtt_test_success')
            ->assertSessionMissing('_old_input.password');

        $this->assertDatabaseCount('mqtt_settings', 0);
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'intent' => 'save',
            'is_active' => '1',
            'mode' => 'external',
            'host' => 'broker.example.com',
            'port' => 1883,
            'client_id_prefix' => 'hydroflow-dashboard',
            'username' => 'hydroflow',
            'password' => 'secret',
            'clean_session' => '1',
            'connect_timeout' => 5,
            'socket_timeout' => 5,
            'keep_alive' => 60,
            'tls_enabled' => '0',
            'tls_verify_peer' => '1',
            'tls_verify_peer_name' => '1',
            'tls_allow_self_signed' => '0',
            'tls_ca_file' => null,
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function settingValues(array $overrides = []): array
    {
        $values = $this->payload($overrides);
        unset($values['intent']);

        return $values;
    }
}
