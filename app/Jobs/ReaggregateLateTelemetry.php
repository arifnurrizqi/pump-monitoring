<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;

class ReaggregateLateTelemetry implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $recordedAt) {}

    public function handle(): void
    {
        $period = Carbon::parse($this->recordedAt);

        foreach (['hourly', 'daily', 'monthly', 'yearly'] as $type) {
            Artisan::call("aggregate:{$type}", ['--period' => $period->toDateTimeString()]);
        }
    }
}
