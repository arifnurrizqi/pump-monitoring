<?php

namespace App\Console\Commands;

use App\Services\MqttService;
use App\Services\MqttTelemetryReceiver;
use Illuminate\Console\Command;
use Throwable;

class SubscribeTelemetry extends Command
{
    protected $signature = 'telemetry:subscribe';

    protected $description = 'Receive gateway telemetry from MQTT';

    public function handle(MqttService $mqtt, MqttTelemetryReceiver $receiver): int
    {
        $topic = config('mqtt.telemetry_topic');

        while (true) {
            try {
                $mqtt->connect(['clean_session' => false], config('mqtt.telemetry_client_id'));
                $mqtt->subscribe($topic, function (string $messageTopic, string $message, bool $retained) use ($receiver): void {
                    $receiver->receive($messageTopic, $message, $retained);
                });
                $this->info("Listening for MQTT telemetry on {$topic}");
                $mqtt->loop();
            } catch (Throwable $exception) {
                report($exception);
                $this->error('MQTT disconnected; reconnecting in 5 seconds.');
            } finally {
                $mqtt->disconnect();
            }

            sleep(5);
        }
    }
}
