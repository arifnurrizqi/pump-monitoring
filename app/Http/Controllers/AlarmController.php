<?php

namespace App\Http\Controllers;

use App\Models\AlarmLog;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AlarmController extends Controller
{
    public function index(Request $request): View
    {
        $query = AlarmLog::with(['device', 'acknowledgedBy']);

        // filter by user device
        $query->whereIn('device_id', auth()->user()->devices->pluck('id'));

        // filter status
        if ($request->status) {
            $query->where('status', $request->status);
        }

         // filter device (INI YANG KAMU LUPA)
        if ($request->device_id) {
            $query->where('device_id', $request->device_id);
        }

        $alarms = $query
            ->latest('started_at')
            ->paginate(15);

        // INI YANG KURANG UNTUK DROPDOWN
        $devices = auth()->user()->devices;

        return view('alarms.index', compact('alarms', 'devices'));
    }

    public function acknowledge(AlarmLog $alarm): RedirectResponse
    {
        // pastikan user punya akses ke device
        $hasAccess = auth()->user()
            ->devices()
            ->where('devices.id', $alarm->device_id)
            ->exists();

        abort_unless($hasAccess, 403);

        if ($alarm->status === 'active') {
            $alarm->update([
                'status' => 'acknowledged',
                'acknowledged_by' => auth()->id(),
                'acknowledged_at' => now(),
            ]);
        }

        return back()->with('success', 'Alarm berhasil di-acknowledge.');
    }
}