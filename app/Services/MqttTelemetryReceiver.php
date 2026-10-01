<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use JsonException;

class MqttTelemetryReceiver
{
    public function __construct(
        private readonly MqttParameterIngestionService $parameterIngestion,
    ) {}

    public function receive(string $topic, string $message, bool $retained = false): void
    {
        if ($retained) {
            return;
        }

        $pattern = '~^'.str_replace('\\+', '([^/]+)', preg_quote(config('mqtt.telemetry_topic'), '~')).'$~';

        if (! preg_match($pattern, $topic, $matches)) {
            Log::warning('MQTT telemetry topic tidak dikenal', ['topic' => $topic]);

            return;
        }

        try {
            $payload = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('MQTT telemetry JSON tidak valid', ['topic' => $topic]);

            return;
        }

        if (! is_array($payload) || array_is_list($payload)
            || ($payload['device_sn'] ?? null) !== $matches[1]
            || ! is_string($payload['message_id'] ?? null)
            || $payload['message_id'] === '') {
            Log::warning('MQTT telemetry identitas tidak valid', ['topic' => $topic]);

            return;
        }

        $result = $this->parameterIngestion->ingest($payload);

        if (! in_array($result, ['accepted', 'duplicate'], true)) {
            Log::warning('MQTT telemetry ditolak', [
                'topic' => $topic,
                'status' => $result,
                'message_id' => $payload['message_id'],
            ]);
        }
    }
}
