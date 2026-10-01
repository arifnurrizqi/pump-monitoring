<?php

namespace App\Jobs;

use App\Models\DeviceCommand;
use App\Services\MqttService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class PublishValveCommand implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [1, 3, 5];

    public function __construct(public int $commandId) {}

    public function handle(MqttService $mqtt): void
    {
        $command = DeviceCommand::with('device')->find($this->commandId);

        if (! $command || in_array($command->status, ['ack', 'expired'], true)) {
            return;
        }

        if ($command->created_at->addSeconds(config('mqtt.valve.queue_timeout'))->isPast()) {
            $command->update(['status' => 'expired']);

            return;
        }

        $command->increment('attempts');

        $expiresAt = now()->addSeconds(config('mqtt.valve.ack_timeout'));
        $command->update(['expires_at' => $expiresAt]);

        $payload = json_encode([
            'device_sn' => $command->device->device_sn,
            'command' => 'set_drain_valve',
            'value' => (int) $command->command_value,
            'command_id' => $command->id,
            'issued_at' => $command->created_at->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
        ], JSON_THROW_ON_ERROR);

        try {
            $mqtt->connect();
            $mqtt->publish(
                "devices/{$command->device->device_sn}/commands/drain-valve",
                $payload,
                1,
                false,
            );
        } finally {
            $mqtt->disconnect();
        }

        $command->newQuery()
            ->whereKey($command->id)
            ->where('status', 'queued')
            ->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
                'updated_at' => now(),
            ]);
    }

    public function failed(?Throwable $exception): void
    {
        DeviceCommand::whereKey($this->commandId)
            ->whereNotIn('status', ['ack', 'expired'])
            ->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception?->getMessage() ?? 'MQTT publish failed.', 0, 1000),
                'updated_at' => now(),
            ]);
    }
}
