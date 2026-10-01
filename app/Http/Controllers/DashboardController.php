<?php

namespace App\Http\Controllers;

use App\Models\AlarmLog;
use App\Models\TelemetryRaw;
use App\Services\ParameterHealthService;
use App\Services\LiveTelemetryState;
use App\Services\SourceLiveState;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Ambil semua device milik user + relasi penting
        $devices = $user->devices()
            ->with(['setting', 'latestData', 'activeAlarms'])
            ->withCount('sources')
            ->get();

        // inject health result ke setiap device
        foreach ($devices as $device) {
            $this->liveState->apply($device);
            $this->sourceLiveState->applyConnection($device);
            $latest = $device->latestData;
            if (! $latest) {
                $device->health = [];
                $device->overall_health = 'unknown';

                continue;
            }
            $device->health = $this->healthService->evaluate($latest);
            $device->overall_health = $this->healthService->waterQuality($device->health);
        }

        // Summary counters
        $totalDevices = $devices->count();
        $onlineCount = 0;
        $pendingCount = 0;
        $offlineCount = 0;

        foreach ($devices as $device) {
            switch ($device->connection_status) {
                case 'online':
                    $onlineCount++;
                    break;

                case 'pending':
                    $pendingCount++;
                    break;

                default:
                    $offlineCount++;
                    break;
            }
        }

        // Optional: device yang bermasalah saja
        $problemDevices = $devices->filter(function ($device) {
            return $device->connection_status !== 'online';
        });

        // Ambil 3 alarm terakhir di menu alarm (milik user)
        $latestAlarms = AlarmLog::with('device')
            ->whereIn('device_id', $devices->pluck('id'))
            ->latest('started_at')
            ->limit(3)
            ->get();

        $totalUsageTodayLiter = $this->totalUsageLiter(
            $devices->pluck('id'),
            now()->startOfDay(),
            now()
        );
        $totalUsageYesterdayLiter = $this->totalUsageLiter(
            $devices->pluck('id'),
            now()->subDay()->startOfDay(),
            now()->subDay()->endOfDay()
        );
        $totalUsageThisMonthLiter = $this->totalUsageLiter(
            $devices->pluck('id'),
            now()->startOfMonth(),
            now()
        );

        foreach ($devices as $device) {
            $state = $device->relationLoaded('liveTelemetryState')
                ? $device->getRelation('liveTelemetryState') : null;
            if (! $state) {
                continue;
            }

            $today = $this->liveState->usage($state, 'day');
            $month = $this->liveState->usage($state, 'month');
            if ($today !== null) {
                $totalUsageTodayLiter += $today - (float) $device->telemetryRaw()
                    ->where('recorded_at', '>=', now()->startOfDay())->sum('usage_liter');
            }
            if ($month !== null) {
                $totalUsageThisMonthLiter += $month - (float) $device->telemetryRaw()
                    ->where('recorded_at', '>=', now()->startOfMonth())->sum('usage_liter');
            }
        }

        $usageTrendPercent = $totalUsageYesterdayLiter > 0
            ? (($totalUsageTodayLiter - $totalUsageYesterdayLiter) / $totalUsageYesterdayLiter) * 100
            : null;

        return view('dashboard.index', [
            'devices' => $devices,
            'totalDevices' => $totalDevices,
            'onlineCount' => $onlineCount,
            'pendingCount' => $pendingCount,
            'offlineCount' => $offlineCount,
            'problemDevices' => $problemDevices,
            'latestAlarms' => $latestAlarms,
            'totalUsageTodayLiter' => $totalUsageTodayLiter,
            'totalUsageThisMonthLiter' => $totalUsageThisMonthLiter,
            'usageTrendPercent' => $usageTrendPercent,
        ]);
    }

    public function __construct(
        private ParameterHealthService $healthService,
        private LiveTelemetryState $liveState,
        private SourceLiveState $sourceLiveState,
    ) {}

    private function totalUsageLiter($deviceIds, $from, $to): float
    {
        if ($deviceIds->isEmpty()) {
            return 0.0;
        }

        return (float) TelemetryRaw::query()
            ->whereIn('telemetry_raw.device_id', $deviceIds)
            ->whereBetween('telemetry_raw.recorded_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(telemetry_raw.usage_liter), 0) AS usage_liter')
            ->value('usage_liter');
    }
}
