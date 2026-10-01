<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class SourceTelemetryUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly int $deviceId,
        public readonly array $sources,
        public readonly int $activeAlarmCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("devices.{$this->deviceId}")];
    }

    public function broadcastAs(): string
    {
        return 'parameters.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'device_id' => $this->deviceId,
            'sources' => $this->sources,
            'active_alarm_count' => $this->activeAlarmCount,
        ];
    }
}
