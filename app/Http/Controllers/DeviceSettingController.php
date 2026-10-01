<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDeviceSettingRequest;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeviceSettingController extends Controller
{
    public function edit(Device $device): View
    {
        $this->authorizeDeviceAccess($device);

        $pivot = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->first()?->pivot;

        if (! $pivot || ! in_array($pivot->role, ['owner', 'operator'], true)) {
            abort(403, 'Anda tidak punya izin untuk mengubah setting device ini.');
        }

        $referer = request()->headers->get('referer');
        if ($referer && parse_url($referer, PHP_URL_HOST) === request()->getHost()) {
            $path = parse_url($referer, PHP_URL_PATH);
            if ($path === '/devices' || preg_match('#^/devices/\d+$#', $path) || $path === '/dashboard') {
                $query = parse_url($referer, PHP_URL_QUERY);
                $refererUrl = $path.($query ? '?'.$query : '');
                session(['device_settings_back_url_'.$device->id => $refererUrl]);
            }
        }
        $backUrl = session('device_settings_back_url_'.$device->id, route('devices.show', $device));

        $device->load('setting');

        if (! $device->setting) {
            $device->setting()->create([
                'alarm_enabled' => true,
                'sampling_interval_sec' => 10,
                'send_interval_sec' => 10,
                'timezone' => 'Asia/Jakarta',
            ]);

            $device->load('setting');
        }

        return view('devices.settings.edit', compact('device', 'backUrl'));
    }

    public function update(UpdateDeviceSettingRequest $request, Device $device): RedirectResponse
    {
        $this->authorizeDeviceAccess($device);

        $pivot = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->first()?->pivot;

        if (! $pivot || ! in_array($pivot->role, ['owner', 'operator'], true)) {
            abort(403, 'Anda tidak punya izin untuk mengubah setting device ini.');
        }

        $device->setting()->updateOrCreate(
            ['device_id' => $device->id],
            [
                'tank_height_cm' => $request->tank_height_cm,
                'tank_volume_liter' => $request->tank_volume_liter,

                'pulse_per_liter' => $request->pulse_per_liter,

                'ph_min' => $request->ph_min,
                'ph_max' => $request->ph_max,

                'turbidity_max' => $request->turbidity_max,

                'sample_flow_min' => $request->sample_flow_min,
                'sample_flow_max' => $request->sample_flow_max,

                'level_min_percent' => $request->level_min_percent,
                'level_max_percent' => $request->level_max_percent,

                'device_voltage_mcu_min' => $request->device_voltage_mcu_min,
                'device_voltage_5v_min' => $request->device_voltage_5v_min,
                'device_voltage_input_min' => $request->device_voltage_input_min,

                'sampling_interval_sec' => $request->sampling_interval_sec,
                'send_interval_sec' => $request->send_interval_sec,

                'alarm_enabled' => $request->boolean('alarm_enabled'),
                'timezone' => $request->timezone,
                'notes' => $request->notes,
            ]
        );

        $backUrl = session('device_settings_back_url_'.$device->id, route('devices.show', $device));
        session()->forget('device_settings_back_url_'.$device->id);

        return redirect()
            ->to($backUrl)
            ->with('success', 'Setting device berhasil diperbarui.');
    }

    private function authorizeDeviceAccess(Device $device): void
    {
        $hasAccess = auth()->user()
            ->devices()
            ->where('devices.id', $device->id)
            ->exists();

        abort_unless($hasAccess, 403, 'Anda tidak punya akses ke device ini.');
    }
}
