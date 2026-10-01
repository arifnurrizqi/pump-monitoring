<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\LiveTelemetryState;
use App\Services\SourceLiveState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(
        private readonly LiveTelemetryState $liveState,
        private readonly SourceLiveState $sourceLiveState,
    ) {}

    public function index(): View
    {
        $devices = auth()->user()
            ->devices()
            ->with(['setting', 'latestData'])
            ->withCount('sources')
            ->latest('devices.created_at')
            ->paginate(5);

        $devices->getCollection()->each(function (Device $device): void {
            $this->liveState->apply($device);
            $this->sourceLiveState->applyConnection($device);
        });

        return view('devices.index', compact('devices'));
    }

    public function create(): View
    {
        $referer = request()->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === request()->getHost()) {
            $path = parse_url($referer, PHP_URL_PATH);
            if (in_array($path, ['/dashboard', '/devices'])) {
                $query = parse_url($referer, PHP_URL_QUERY);
                $refererUrl = $path.($query ? '?'.$query : '');
                session(['device_create_back_url' => $refererUrl]);
            }
        }
        $backUrl = session('device_create_back_url', route('devices.index'));

        return view('devices.create', compact('backUrl'));
    }

    public function store(StoreDeviceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $device = Device::where('device_sn', $data['device_sn'])->first();

        if ($device) {
            $alreadyAttached = $device->users()
                ->where('users.id', auth()->id())
                ->exists();

            if ($alreadyAttached) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'device_sn' => 'Device dengan SN tersebut sudah terdaftar pada akun Anda.',
                    ]);
            }

            $hasOwner = $device->users()
                ->wherePivot('role', 'owner')
                ->exists();

            if ($hasOwner) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'device_sn' => 'Device dengan SN tersebut sudah dimiliki akun lain. Minta owner untuk membagikan akses.',
                    ]);
            }

            $device->update([
                'device_tag' => $data['device_tag'],
                'location' => $data['location'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        } else {
            $device = Device::create([
                'device_sn' => $data['device_sn'],
                'api_token' => Str::random(60),
                'device_tag' => $data['device_tag'],
                'location' => $data['location'] ?? null,
                'description' => $data['description'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        }

        $device->users()->attach(auth()->id(), [
            'role' => 'owner',
        ]);

        $device->setting()->firstOrCreate(
            ['device_id' => $device->id],
            [
                'alarm_enabled' => true,
                'sampling_interval_sec' => 10,
                'send_interval_sec' => 10,
                'timezone' => 'Asia/Jakarta',
            ]
        );

        $backUrl = session('device_create_back_url', route('devices.index'));
        session()->forget('device_create_back_url');

        return redirect()
            ->to($backUrl)
            ->with('success', 'Device berhasil ditambahkan.');
    }

    public function show(Device $device): View
    {
        $this->authorizeDeviceAccess($device);

        $referer = request()->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === request()->getHost()) {
            $path = parse_url($referer, PHP_URL_PATH);
            if (in_array($path, ['/dashboard', '/devices', '/alarms', '/datalogger'])) {
                $query = parse_url($referer, PHP_URL_QUERY);
                $refererUrl = $path.($query ? '?'.$query : '');
                session(['device_detail_back_url_'.$device->id => $refererUrl]);
            }
        }
        $backUrl = session('device_detail_back_url_'.$device->id, route('devices.index'));

        $device->load([
            'setting',
            'site',
            'sources.asset',
            'sources.parameters',
            'latestTelemetry',
            'latestData',
            'activeAlarms',
            'users',
        ]);
        $liveState = $this->liveState->apply($device);
        $sourceReadings = $this->sourceLiveState->forDevice($device);
        $sourceHistory = [];
        foreach ($device->sources as $source) {
            $sourceHistory[$source->id] = $source->intervals()
                ->latest('bucket_start')->limit(10)->get();
        }

        $myRole = $this->getUserDeviceRole($device);

        $usageTodayLiter = (float) $device->telemetryRaw()
            ->whereBetween('recorded_at', [now()->startOfDay(), now()])
            ->sum('usage_liter');
        $usageThisMonthLiter = (float) $device->telemetryRaw()
            ->whereBetween('recorded_at', [now()->startOfMonth(), now()])
            ->sum('usage_liter');
        if ($liveState) {
            $usageTodayLiter = $this->liveState->usage($liveState, 'day') ?? $usageTodayLiter;
            $usageThisMonthLiter = $this->liveState->usage($liveState, 'month') ?? $usageThisMonthLiter;
        }

        // ── Chart data ────────────────────────────────────────────
        // 1 H  → raw telemetry for the last 1 hour
        $raw1h = $device->telemetryRaw()
            ->where('recorded_at', '>=', now()->subHours(1))
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'ph', 'turbidity', 'level_percent', 'usage_liter']);

        // 7 H  → raw telemetry for the last 7 hours
        $raw7h = $device->telemetryRaw()
            ->where('recorded_at', '>=', now()->subHours(7))
            ->orderBy('recorded_at')
            ->get(['recorded_at', 'ph', 'turbidity', 'level_percent']);

        // 24 H  → hourly aggregates for the last 24 hours
        $hourly24h = $device->telemetryHourly()
            ->where('period_start', '>=', now()->subHours(24)->startOfHour())
            ->orderBy('period_start')
            ->get(['period_start', 'avg_ph', 'avg_turbidity', 'avg_level_percent', 'usage_liter']);

        // 7 D  → daily aggregates for the last 7 days
        $daily7d = $device->telemetryDaily()
            ->where('period_start', '>=', now()->subDays(7)->startOfDay())
            ->orderBy('period_start')
            ->get(['period_start', 'avg_ph', 'avg_turbidity', 'avg_level_percent', 'usage_liter']);

        // 30 D → daily aggregates for the last 30 days
        $daily30d = $device->telemetryDaily()
            ->where('period_start', '>=', now()->subDays(30)->startOfDay())
            ->orderBy('period_start')
            ->get(['period_start', 'avg_ph', 'avg_turbidity', 'avg_level_percent', 'usage_liter']);

        $mapRaw1h = fn ($r) => [
            'label' => $r->recorded_at->format('H:i:s'),
            'ph' => $r->ph !== null ? round((float) $r->ph, 2) : null,
            'turbidity' => $r->turbidity !== null ? round((float) $r->turbidity, 2) : null,
            'level' => $r->level_percent !== null ? round((float) $r->level_percent, 1) : null,
        ];

        $mapRaw7h = fn ($r) => [
            'label' => $r->recorded_at->format('H:i'),
            'ph' => $r->ph !== null ? round((float) $r->ph, 2) : null,
            'turbidity' => $r->turbidity !== null ? round((float) $r->turbidity, 2) : null,
            'level' => $r->level_percent !== null ? round((float) $r->level_percent, 1) : null,
        ];

        $mapHourly = fn ($r) => [
            'label' => $r->period_start->format('H:i'),
            'ph' => round((float) $r->avg_ph, 2),
            'turbidity' => round((float) $r->avg_turbidity, 2),
            'level' => round((float) $r->avg_level_percent, 1),
        ];

        $mapDaily = fn ($r) => [
            'label' => $r->period_start->format('d M'),
            'ph' => round((float) $r->avg_ph, 2),
            'turbidity' => round((float) $r->avg_turbidity, 2),
            'level' => round((float) $r->avg_level_percent, 1),
        ];

        $usage1h = $raw1h
            ->groupBy(function ($row) {
                $bucket = $row->recorded_at->copy()->startOfMinute();
                $bucket->subMinutes($bucket->minute % 5);

                return $bucket->timestamp;
            })
            ->map(function ($rows) {
                $bucket = $rows->first()->recorded_at->copy()->startOfMinute();
                $bucket->subMinutes($bucket->minute % 5);

                return [
                    'label' => $bucket->format('H:i'),
                    'usage' => round((float) $rows->sum('usage_liter'), 6),
                ];
            })
            ->values();

        $currentHourStart = now()->startOfHour();
        $currentHourUsage = round((float) $raw1h
            ->filter(fn ($row) => $row->recorded_at->gte($currentHourStart))
            ->sum('usage_liter'), 6);

        $hourlyUsage = function ($since) use ($hourly24h, $currentHourStart, $currentHourUsage) {
            return $hourly24h
                ->filter(fn ($row) => $row->period_start->gte($since) && $row->period_start->lt($currentHourStart))
                ->map(fn ($row) => [
                    'label' => $row->period_start->format('H:i'),
                    'usage' => round((float) $row->usage_liter, 6),
                ])
                ->values()
                ->push([
                    'label' => $currentHourStart->format('H:i'),
                    'usage' => $currentHourUsage,
                ]);
        };

        $currentDayStart = now()->startOfDay();
        $dailyUsage = function ($rows, $since) use ($currentDayStart, $usageTodayLiter) {
            return $rows
                ->filter(fn ($row) => $row->period_start->gte($since) && $row->period_start->lt($currentDayStart))
                ->map(fn ($row) => [
                    'label' => $row->period_start->format('d M'),
                    'usage' => round((float) $row->usage_liter, 6),
                ])
                ->values()
                ->push([
                    'label' => $currentDayStart->format('d M'),
                    'usage' => round($usageTodayLiter, 6),
                ]);
        };

        $chartData = [
            '1h' => $raw1h->map($mapRaw1h)->values(),
            '7h' => $raw7h->map($mapRaw7h)->values(),
            '24h' => $hourly24h->map($mapHourly)->values(),
            '7d' => $daily7d->map($mapDaily)->values(),
            '30d' => $daily30d->map($mapDaily)->values(),
            'usage' => [
                '1h' => $usage1h,
                '7h' => $hourlyUsage(now()->subHours(7)->startOfHour()),
                '24h' => $hourlyUsage(now()->subHours(24)->startOfHour()),
                '7d' => $dailyUsage($daily7d, now()->subDays(7)->startOfDay()),
                '30d' => $dailyUsage($daily30d, now()->subDays(30)->startOfDay()),
            ],
            'thresholds' => [
                'ph_min' => $device->setting?->ph_min !== null ? round((float) $device->setting->ph_min, 2) : null,
                'ph_max' => $device->setting?->ph_max !== null ? round((float) $device->setting->ph_max, 2) : null,
                'turbidity_max' => $device->setting?->turbidity_max !== null ? round((float) $device->setting->turbidity_max, 2) : null,
                'level_min' => $device->setting?->level_min_percent !== null ? round((float) $device->setting->level_min_percent, 1) : null,
                'level_max' => $device->setting?->level_max_percent !== null ? round((float) $device->setting->level_max_percent, 1) : null,
            ],
        ];

        return view('devices.show', compact(
            'device',
            'myRole',
            'chartData',
            'backUrl',
            'usageTodayLiter',
            'usageThisMonthLiter',
            'sourceReadings',
            'sourceHistory',
        ));
    }

    public function edit(Device $device): View
    {
        $this->authorizeDeviceAccess($device);

        $referer = request()->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === request()->getHost()) {
            $path = parse_url($referer, PHP_URL_PATH);
            if ($path === '/devices' || preg_match('#^/devices/\d+$#', $path) || $path === '/dashboard') {
                $query = parse_url($referer, PHP_URL_QUERY);
                $refererUrl = $path.($query ? '?'.$query : '');
                session(['device_edit_back_url_'.$device->id => $refererUrl]);
            }
        }
        $backUrl = session('device_edit_back_url_'.$device->id, route('devices.show', $device));

        return view('devices.edit', compact('device', 'backUrl'));
    }

    public function update(UpdateDeviceRequest $request, Device $device): RedirectResponse
    {
        $this->authorizeDeviceAccess($device);

        $pivot = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->first()?->pivot;

        if (! $pivot || ! in_array($pivot->role, ['owner', 'operator'], true)) {
            abort(403, 'Anda tidak punya izin untuk mengubah device ini.');
        }

        $device->update([
            'device_tag' => $request->device_tag,
            'location' => $request->location,
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ]);

        $backUrl = session('device_edit_back_url_'.$device->id, route('devices.show', $device));
        session()->forget('device_edit_back_url_'.$device->id);

        return redirect()
            ->to($backUrl)
            ->with('success', 'Device berhasil diperbarui.');
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorizeDeviceAccess($device);

        $pivot = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->first()?->pivot;

        if (! $pivot || $pivot->role !== 'owner') {
            abort(403, 'Hanya owner yang dapat menghapus device ini.');
        }

        // Hapus relasi user dengan device
        $device->users()->detach(auth()->id());

        // Kalau sudah tidak punya user sama sekali, hapus device
        if ($device->users()->count() === 0) {
            $device->delete();
        }

        return redirect()
            ->route('devices.index')
            ->with('success', 'Device berhasil dihapus.');
    }

    private function authorizeDeviceAccess(Device $device): void
    {
        $hasAccess = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->exists();

        abort_unless($hasAccess, 403, 'Anda tidak punya akses ke device ini.');
    }

    private function getUserDeviceRole(Device $device): ?string
    {
        $relation = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->first();

        return $relation?->pivot?->role;
    }

    private function authorizeOwner(Device $device): void
    {
        $this->authorizeDeviceAccess($device);

        $role = $this->getUserDeviceRole($device);

        abort_unless($role === 'owner', 403, 'Hanya owner yang dapat melakukan aksi ini.');
    }

    public function share(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeOwner($device);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role' => ['required', 'in:viewer,operator'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return back()->withErrors([
                'email' => 'User dengan email tersebut tidak ditemukan.',
            ]);
        }

        if ($user->id === auth()->id()) {
            return back()->withErrors([
                'email' => 'Owner tidak perlu membagikan akses ke dirinya sendiri.',
            ]);
        }

        $existing = $device->users()->where('users.id', $user->id)->first();

        if ($existing) {
            return back()->withErrors([
                'email' => 'User tersebut sudah memiliki akses ke device ini.',
            ]);
        }

        $device->users()->attach($user->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Akses device berhasil dibagikan.');
    }

    public function updateAccess(Request $request, Device $device, User $user): RedirectResponse
    {
        $this->authorizeOwner($device);

        $validated = $request->validate([
            'role' => ['required', 'in:viewer,operator'],
        ]);

        $existing = $device->users()->where('users.id', $user->id)->first();

        if (! $existing) {
            return back()->withErrors([
                'role' => 'User tersebut tidak memiliki akses ke device ini.',
            ]);
        }

        $currentRole = $existing->pivot->role;

        if ($currentRole === 'owner') {
            return back()->withErrors([
                'role' => 'Role owner tidak dapat diubah dari menu ini.',
            ]);
        }

        $device->users()->updateExistingPivot($user->id, [
            'role' => $validated['role'],
        ]);

        return back()->with('success', 'Role akses berhasil diperbarui.');
    }

    public function removeAccess(Device $device, User $user): RedirectResponse
    {
        $this->authorizeOwner($device);

        $existing = $device->users()->where('users.id', $user->id)->first();

        if (! $existing) {
            return back()->withErrors([
                'email' => 'User tersebut tidak memiliki akses ke device ini.',
            ]);
        }

        if ($existing->pivot->role === 'owner') {
            return back()->withErrors([
                'email' => 'Owner tidak dapat dihapus dari menu ini.',
            ]);
        }

        $device->users()->detach($user->id);

        return back()->with('success', 'Akses user berhasil dihapus.');
    }
}
