<?php

namespace App\Http\Controllers;

use App\Models\AlarmLog;
use App\Models\DataSource;
use App\Models\Device;
use App\Models\ProductionSite;
use App\Models\SourceParameter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class MonitoringConfigurationController extends Controller
{
    public function index(Device $device): View
    {
        $this->authorizeAccess($device);
        $device->load(['site.assets', 'setting', 'sources.asset', 'sources.parameters']);
        $canEdit = auth()->user()->devices()->where('devices.id', $device->id)
            ->wherePivotIn('role', ['owner', 'operator'])->exists();
        $availableSites = ProductionSite::whereIn('id', auth()->user()->devices()
            ->whereNotNull('production_site_id')->pluck('production_site_id'))->orderBy('name')->get();

        return view('devices.monitoring', compact('device', 'canEdit', 'availableSites'));
    }

    public function site(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeEdit($device);
        if ($request->filled('existing_site_id')) {
            $allowed = auth()->user()->devices()->whereNotNull('production_site_id')
                ->pluck('production_site_id')->all();
            $data = $request->validate([
                'existing_site_id' => ['required', 'integer', Rule::in($allowed)],
            ]);
            $device->update(['production_site_id' => $data['existing_site_id']]);

            return back()->with('success', 'Lokasi produksi dipilih.');
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:60', 'alpha_dash', 'unique:production_sites,code'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $site = ProductionSite::create($data);
        $device->update(['production_site_id' => $site->id]);

        return back()->with('success', 'Lokasi produksi disimpan.');
    }

    public function gateway(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeEdit($device);
        $data = $request->validate([
            'send_interval_sec' => ['required', 'integer', 'between:1,3600'],
        ]);
        $device->setting()->updateOrCreate(['device_id' => $device->id], $data);

        return back()->with('success', 'Interval pengiriman gateway diperbarui.');
    }

    public function asset(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeEdit($device);
        abort_unless($device->production_site_id, 422, 'Atur lokasi produksi dahulu.');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['pump', 'motor', 'other'])],
            'phase_count' => ['nullable', 'integer', Rule::in([1, 3])],
        ]);
        $device->site->assets()->create($data);

        return back()->with('success', 'Aset ditambahkan.');
    }

    public function source(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeEdit($device);
        $data = $request->validate([
            'source_key' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('data_sources')->where('device_id', $device->id)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['power_meter', 'flow', 'pressure', 'temperature', 'other'])],
            'monitored_asset_id' => ['nullable', 'integer', Rule::exists('monitored_assets', 'id')->where('production_site_id', $device->production_site_id)],
        ]);
        $source = $device->sources()->create($data);
        if ($source->type === 'power_meter') {
            $phaseCount = $source->asset?->phase_count ?? 1;
            foreach ($this->powerMeterParameters($phaseCount) as $index => [$key, $label, $unit]) {
                $source->parameters()->create(compact('key', 'label', 'unit') + ['sort_order' => $index]);
            }
        }

        return back()->with('success', 'Sumber data ditambahkan.');
    }

    public function parameter(Request $request, Device $device, DataSource $source): RedirectResponse
    {
        $this->authorizeEdit($device);
        abort_unless($source->device_id === $device->id, 404);
        $maxRules = ['nullable', 'numeric'];
        if ($request->filled('alarm_min')) {
            $maxRules[] = 'gt:alarm_min';
        }
        $data = $request->validate([
            'key' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9_]+$/', Rule::unique('source_parameters')->where('data_source_id', $source->id)],
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:24'],
            'alarm_min' => ['nullable', 'numeric'],
            'alarm_max' => $maxRules,
        ]);
        $data['alarm_enabled'] = $request->boolean('alarm_enabled');
        $source->parameters()->create($data);

        return back()->with('success', 'Parameter ditambahkan.');
    }

    public function updateParameter(Request $request, Device $device, DataSource $source, SourceParameter $parameter): RedirectResponse
    {
        $this->authorizeEdit($device);
        abort_unless($source->device_id === $device->id && $parameter->data_source_id === $source->id, 404);
        $maxRules = ['nullable', 'numeric'];
        if ($request->filled('alarm_min')) {
            $maxRules[] = 'gt:alarm_min';
        }
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:24'],
            'alarm_min' => ['nullable', 'numeric'],
            'alarm_max' => $maxRules,
        ]);
        $data['alarm_enabled'] = $request->boolean('alarm_enabled');
        $parameter->update($data);
        AlarmLog::where('source_parameter_id', $parameter->id)
            ->whereIn('status', ['active', 'acknowledged'])
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
        try {
            $cache = Cache::store(config('mqtt.live_cache_store'));
            $key = "telemetry:v2:{$device->id}";
            $cache->lock("{$key}:lock", 15)->block(5, function () use ($cache, $key, $source, $parameter): void {
                $state = $cache->get($key);
                if ($state && isset($state['sources'][$source->id])) {
                    $state['sources'][$source->id]['alarm_counts'][$parameter->id] = 0;
                    $cache->forever($key, $state);
                }
            });
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with('success', 'Parameter diperbarui.');
    }

    private function authorizeAccess(Device $device): void
    {
        abort_unless(auth()->user()->devices()->where('devices.id', $device->id)->exists(), 403);
    }

    private function authorizeEdit(Device $device): void
    {
        abort_unless(auth()->user()->devices()->where('devices.id', $device->id)
            ->wherePivotIn('role', ['owner', 'operator'])->exists(), 403);
    }

    private function powerMeterParameters(int $phaseCount): array
    {
        $parameters = [];
        foreach (range(1, $phaseCount === 3 ? 3 : 1) as $phase) {
            $parameters[] = ["voltage_l{$phase}", "Tegangan L{$phase}", 'V'];
            $parameters[] = ["current_l{$phase}", "Arus L{$phase}", 'A'];
        }

        return [...$parameters,
            ['active_power', 'Daya aktif', 'kW'],
            ['power_factor', 'Faktor daya', null],
            ['frequency', 'Frekuensi', 'Hz'],
            ['energy_total', 'Energi total', 'kWh'],
        ];
    }
}
