<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TelemetryUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $deviceId,
        public readonly array $snapshot,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("devices.{$this->deviceId}")];
    }

    public function broadcastAs(): string
    {
        return 'telemetry.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'snapshot' => $this->snapshot,
        ];
    }
}
