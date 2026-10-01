<?php

namespace App\Services;

use App\Models\MqttSetting;

class MqttConfigurationService
{
    /** @return array<string, mixed> */
    public function resolve(?array $overrides = null): array
    {
        $configuration = $this->environmentConfiguration();
        $setting = MqttSetting::query()->first();

        if ($setting?->is_active) {
            $configuration = array_replace($configuration, $this->databaseConfiguration($setting));
            $configuration['source'] = 'database';
        }

        if ($overrides !== null) {
            $configuration = array_replace($configuration, $overrides);
            $configuration['source'] = 'test';
        }

        return $configuration;
    }

    /** @return array<string, mixed> */
    public function environmentConfiguration(): array
    {
        return [
            'source' => 'environment',
            'mode' => 'environment',
            'host' => config('mqtt.host'),
            'port' => (int) config('mqtt.port'),
            'client_id_prefix' => config('mqtt.client_id_prefix'),
            'username' => config('mqtt.username'),
            'password' => config('mqtt.password'),
            'clean_session' => (bool) config('mqtt.clean_session'),
            'connect_timeout' => (int) config('mqtt.connect_timeout'),
            'socket_timeout' => (int) config('mqtt.socket_timeout'),
            'keep_alive' => (int) config('mqtt.keep_alive'),
            'tls_enabled' => (bool) config('mqtt.tls.enabled'),
            'tls_verify_peer' => (bool) config('mqtt.tls.verify_peer'),
            'tls_verify_peer_name' => (bool) config('mqtt.tls.verify_peer_name'),
            'tls_allow_self_signed' => (bool) config('mqtt.tls.allow_self_signed'),
            'tls_ca_file' => config('mqtt.tls.ca_file'),
            'tls_client_certificate_file' => config('mqtt.tls.client_certificate_file'),
            'tls_client_certificate_key_file' => config('mqtt.tls.client_certificate_key_file'),
            'tls_client_certificate_key_passphrase' => config('mqtt.tls.client_certificate_key_passphrase'),
        ];
    }

    /** @return array<string, mixed> */
    private function databaseConfiguration(MqttSetting $setting): array
    {
        return [
            'mode' => $setting->mode,
            'host' => $setting->host,
            'port' => $setting->port,
            'client_id_prefix' => $setting->client_id_prefix,
            'username' => $setting->username,
            'password' => $setting->password,
            'clean_session' => $setting->clean_session,
            'connect_timeout' => $setting->connect_timeout,
            'socket_timeout' => $setting->socket_timeout,
            'keep_alive' => $setting->keep_alive,
            'tls_enabled' => $setting->tls_enabled,
            'tls_verify_peer' => $setting->tls_verify_peer,
            'tls_verify_peer_name' => $setting->tls_verify_peer_name,
            'tls_allow_self_signed' => $setting->tls_allow_self_signed,
            'tls_ca_file' => $setting->tls_ca_file,
        ];
    }
}
