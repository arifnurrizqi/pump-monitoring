<?php

namespace App\Console\Commands;

use App\Services\TelemetryBucketService;
use App\Services\SourceTelemetryBucketService;
use Illuminate\Console\Command;

class FlushTelemetryBuckets extends Command
{
    protected $signature = 'telemetry:flush-buckets';

    protected $description = 'Persist closed MQTT telemetry intervals';

    public function handle(TelemetryBucketService $buckets, SourceTelemetryBucketService $sourceBuckets): int
    {
        $buckets->flushClosedBuckets();
        $sourceBuckets->flushClosed();

        return self::SUCCESS;
    }
}
