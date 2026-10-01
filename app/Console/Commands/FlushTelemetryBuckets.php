<?php

namespace App\Console\Commands;

use App\Services\SourceTelemetryBucketService;
use Illuminate\Console\Command;

class FlushTelemetryBuckets extends Command
{
    protected $signature = 'telemetry:flush-buckets';

    protected $description = 'Persist closed MQTT telemetry intervals';

    public function handle(SourceTelemetryBucketService $sourceBuckets): int
    {
        $sourceBuckets->flushClosed();

        return self::SUCCESS;
    }
}
