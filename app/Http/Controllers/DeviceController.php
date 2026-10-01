<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Models\User;
use App\Services\SourceLiveState;
use App\Services\DeviceChartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeviceController extends Controller
{
    public function __construct(
        private readonly SourceLiveState $sourceLiveState,
        private readonly DeviceChartService $deviceCharts,
    ) {}

    public function index(): View
    {
        $devices = auth()->user()
            ->devices()
            ->with(['setting', 'site', 'activeAlarms'])
            ->withCount('sources')
            ->latest('devices.created_at')
            ->paginate(5);

        $devices->getCollection()->each(function (Device $device): void {
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
                'send_interval_sec' => 10,
            ]
        );

        session()->forget('device_create_back_url');

        return redirect()
            ->route('devices.monitoring.index', $device)
            ->with('success', 'Gateway ditambahkan. Atur lokasi produksi dan sumber datanya.');
    }

    public function show(Device $device): View
    {
        $this->authorizeDeviceAccess($device);

        $device->load([
            'site', 'setting', 'sources.asset', 'sources.parameters',
            'activeAlarms', 'users',
        ]);
        $sourceReadings = $this->sourceLiveState->forDevice($device);
        $sourceHistory = [];
        foreach ($device->sources as $source) {
            $sourceHistory[$source->id] = $source->intervals()
                ->latest('bucket_start')->limit(60)->get()->reverse()->values();
        }
        $chartGroups = $this->deviceCharts->groups($device);
        $myRole = $this->getUserDeviceRole($device);
        $backUrl = route('devices.index');

        return view('devices.show', compact(
            'device', 'myRole', 'backUrl', 'sourceReadings', 'sourceHistory', 'chartGroups',
        ));
    }

    public function charts(Request $request, Device $device): JsonResponse
    {
        $this->authorizeDeviceAccess($device);
        $range = $request->validate([
            'range' => ['sometimes', 'in:1h,12h,1d,7d,1mo'],
        ])['range'] ?? '1h';
        $device->load('sources.parameters');

        return response()->json([
            'range' => $range,
            'groups' => $this->deviceCharts->history($device, $range),
        ]);
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

