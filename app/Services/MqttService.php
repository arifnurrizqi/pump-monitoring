<?php

namespace App\Services;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use RuntimeException;

class MqttService
{
    protected ?MqttClient $client = null;

    public function __construct(
        private readonly MqttConfigurationService $configuration,
    ) {}

    /** @param array<string, mixed>|null $overrides */
    public function connect(?array $overrides = null, ?string $clientIdOverride = null): void
    {
        $mqtt = $this->configuration->resolve($overrides);
        if (! is_string($mqtt['host']) || trim($mqtt['host']) === '') {
            throw new RuntimeException('Host broker MQTT eksternal belum dikonfigurasi. Isi MQTT_HOST atau pengaturan MQTT di dashboard.');
        }
        $clientId = $clientIdOverride ?? sprintf(
            '%s-%s',
            $mqtt['client_id_prefix'],
            bin2hex(random_bytes(4)),
        );

        $connectionSettings = (new ConnectionSettings)
            ->setUsername($this->optionalString($mqtt['username']))
            ->setPassword($this->optionalString($mqtt['password']))
            ->setConnectTimeout($mqtt['connect_timeout'])
            ->setSocketTimeout($mqtt['socket_timeout'])
            ->setKeepAliveInterval($mqtt['keep_alive'])
            ->setUseTls($mqtt['tls_enabled'])
            ->setTlsVerifyPeer($mqtt['tls_verify_peer'])
            ->setTlsVerifyPeerName($mqtt['tls_verify_peer_name'])
            ->setTlsSelfSignedAllowed($mqtt['tls_allow_self_signed'])
            ->setTlsCertificateAuthorityFile($this->optionalString($mqtt['tls_ca_file']))
            ->setTlsClientCertificateFile($this->optionalString($mqtt['tls_client_certificate_file']))
            ->setTlsClientCertificateKeyFile($this->optionalString($mqtt['tls_client_certificate_key_file']))
            ->setTlsClientCertificateKeyPassphrase($this->optionalString($mqtt['tls_client_certificate_key_passphrase']))
            ->setLastWillTopic(config('mqtt.last_will.topic'))
            ->setLastWillMessage(config('mqtt.last_will.message'))
            ->setLastWillQualityOfService(config('mqtt.last_will.qos'))
            ->setRetainLastWill(config('mqtt.last_will.retain'));

        $this->client = new MqttClient(
            $mqtt['host'],
            $mqtt['port'],
            $clientId,
            MqttClient::MQTT_3_1_1,
        );

        $this->client->connect($connectionSettings, $mqtt['clean_session']);
    }

    /** @param array<string, mixed> $configuration */
    public function testConnection(array $configuration): int
    {
        $startedAt = hrtime(true);

        try {
            $this->connect($configuration);

            return (int) round((hrtime(true) - $startedAt) / 1_000_000);
        } finally {
            $this->disconnect();
        }
    }

    public function publish(string $topic, string $message, int $qos = 0, bool $retain = false): void
    {
        if (! $this->client) {
            $this->connect();
        }

        $this->client->publish($topic, $message, $qos, $retain);
    }

    public function subscribe(string $topic, callable $callback, int $qos = 1): void
    {
        if (! $this->client) {
            $this->connect();
        }

        $this->client->subscribe($topic, $callback, $qos);
    }

    public function loop(): void
    {
        $this->client?->loop(true);
    }

    public function disconnect(): void
    {
        if ($this->client?->isConnected()) {
            $this->client->disconnect();
        }

        $this->client = null;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }
}
