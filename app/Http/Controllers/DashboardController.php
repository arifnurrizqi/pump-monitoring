<?php

namespace App\Http\Controllers;

use App\Models\AlarmLog;
use App\Services\SourceLiveState;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly SourceLiveState $sourceLiveState) {}

    public function index(): View
    {
        $devices = auth()->user()->devices()
            ->with(['site', 'setting', 'activeAlarms', 'sources.asset', 'sources.parameters'])
            ->withCount('sources')
            ->orderBy('device_tag')
            ->get();

        foreach ($devices as $device) {
            $device->setRelation('sourceReadings', $this->sourceLiveState->forDevice($device));
        }

        $deviceIds = $devices->pluck('id');
        $activeAlarmCount = AlarmLog::whereIn('device_id', $deviceIds)
            ->where('status', 'active')->count();
        $latestAlarms = AlarmLog::with('device')
            ->whereIn('device_id', $deviceIds)
            ->where('status', 'active')
            ->latest('started_at')->limit(5)->get();

        return view('dashboard.index', [
            'devices' => $devices,
            'onlineCount' => $devices->where('connection_status', 'online')->count(),
            'pendingCount' => $devices->where('connection_status', 'pending')->count(),
            'offlineCount' => $devices->where('connection_status', 'offline')->count(),
            'sourceCount' => $devices->sum('sources_count'),
            'activeAlarmCount' => $activeAlarmCount,
            'latestAlarms' => $latestAlarms,
        ]);
    }
}
