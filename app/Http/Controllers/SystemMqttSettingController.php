<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMqttSettingRequest;
use App\Models\MqttSetting;
use App\Services\MqttConfigurationService;
use App\Services\MqttService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class SystemMqttSettingController extends Controller
{
    public function edit(MqttConfigurationService $configuration): View
    {
        $this->authorizeAdmin();

        $setting = MqttSetting::query()->with('updatedBy')->first();
        $resolved = $configuration->resolve();

        return view('settings.mqtt.edit', compact('setting', 'resolved'));
    }

    public function update(
        UpdateMqttSettingRequest $request,
        MqttConfigurationService $configuration,
        MqttService $mqtt,
    ): RedirectResponse {
        $current = MqttSetting::query()->first();
        $values = $this->values($request, $current, $configuration);

        if ($request->validated('intent') === 'test') {
            try {
                $latency = $mqtt->testConnection($values);

                return back()
                    ->withInput($request->except('password'))
                    ->with('mqtt_test_success', "Broker terhubung dalam {$latency} ms. Konfigurasi belum disimpan.");
            } catch (Throwable $exception) {
                report($exception);

                return back()
                    ->withInput($request->except('password'))
                    ->with('mqtt_test_error', $this->friendlyConnectionError($exception));
            }
        }

        $values['is_active'] = $request->boolean('is_active');
        $values['updated_by'] = $request->user()->id;

        MqttSetting::query()->updateOrCreate(['id' => 1], $values);
        return redirect()
            ->route('settings.mqtt.edit')
            ->with('success', 'Konfigurasi MQTT disimpan. Restart proses telemetry:subscribe agar koneksi penerima menggunakan broker baru.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->is_admin, 403, 'Hanya admin sistem yang dapat mengelola broker MQTT.');
    }

    /** @return array<string, mixed> */
    private function values(
        UpdateMqttSettingRequest $request,
        ?MqttSetting $current,
        MqttConfigurationService $configuration,
    ): array {
        $validated = $request->validated();
        $fallback = $configuration->environmentConfiguration();

        $password = $validated['password'] ?? null;
        if (! is_string($password) || $password === '') {
            $password = $current?->password ?? $fallback['password'];
        }

        return [
            'mode' => $validated['mode'],
            'host' => $validated['host'],
            'port' => (int) $validated['port'],
            'client_id_prefix' => $validated['client_id_prefix'],
            'username' => $validated['username'] ?? null,
            'password' => $password,
            'clean_session' => $request->boolean('clean_session'),
            'connect_timeout' => (int) $validated['connect_timeout'],
            'socket_timeout' => (int) $validated['socket_timeout'],
            'keep_alive' => (int) $validated['keep_alive'],
            'tls_enabled' => $request->boolean('tls_enabled'),
            'tls_verify_peer' => $request->boolean('tls_verify_peer'),
            'tls_verify_peer_name' => $request->boolean('tls_verify_peer_name'),
            'tls_allow_self_signed' => $request->boolean('tls_allow_self_signed'),
            'tls_ca_file' => $validated['tls_ca_file'] ?? null,
        ];
    }

    private function friendlyConnectionError(Throwable $exception): string
    {
        $message = mb_strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'authentication'), str_contains($message, 'not authorized') => 'Koneksi ditolak. Periksa username dan password broker.',
            str_contains($message, 'certificate'), str_contains($message, 'ssl'), str_contains($message, 'tls') => 'Koneksi TLS gagal. Periksa hostname, sertifikat CA, dan opsi verifikasi.',
            str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'Koneksi timeout. Periksa host, port, firewall, dan jalur jaringan broker.',
            str_contains($message, 'connection refused') => 'Koneksi ditolak oleh host. Pastikan broker aktif dan port sudah benar.',
            default => 'Tidak dapat terhubung ke broker. Periksa host, port, autentikasi, TLS, firewall, dan log aplikasi.',
        };
    }
}
