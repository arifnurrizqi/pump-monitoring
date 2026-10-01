<x-app-layout>
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold leading-tight text-gray-900">MQTT Broker</h1>
            <p class="mt-1 text-sm text-gray-500">Konfigurasi broker global untuk seluruh command perangkat.</p>
        </div>
        <a href="{{ route('profile.edit') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50">
            Account Settings
        </a>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('mqtt_test_success'))
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ session('mqtt_test_success') }}</div>
    @endif
    @if(session('mqtt_test_error'))
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">{{ session('mqtt_test_error') }}</div>
    @endif

    @php
        $value = fn (string $key) => old($key, $setting?->{$key} ?? $resolved[$key] ?? null);
        $databaseActive = (bool) ($setting?->is_active ?? false);
    @endphp

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <form method="POST" action="{{ route('settings.mqtt.update') }}" class="space-y-6 xl:col-span-2">
            @csrf
            @method('PUT')

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Connection</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Gunakan konfigurasi web atau kembali ke nilai environment server.</p>
                </div>
                <div class="space-y-5 p-6">
                    <label class="flex items-start justify-between gap-5 rounded-xl border border-blue-100 bg-blue-50/60 p-4">
                        <span>
                            <span class="block text-sm font-semibold text-gray-900">Use web configuration</span>
                            <span class="mt-1 block text-xs leading-relaxed text-gray-500">Jika dimatikan, command MQTT memakai konfigurasi dari <code>.env</code>.</span>
                        </span>
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked((bool) old('is_active', $databaseActive)) class="mt-1 h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </label>

                    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="mode" value="Broker Type" />
                            <select id="mode" name="mode" class="mt-1 block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="internal" @selected($value('mode') === 'internal')>Internal / Docker</option>
                                <option value="external" @selected($value('mode') === 'external')>External / Separate Server</option>
                            </select>
                            <x-input-error :messages="$errors->get('mode')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="client_id_prefix" value="Client ID Prefix" />
                            <x-text-input id="client_id_prefix" name="client_id_prefix" type="text" class="mt-1 block w-full" :value="$value('client_id_prefix')" required />
                            <x-input-error :messages="$errors->get('client_id_prefix')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="host" value="Broker Host" />
                            <x-text-input id="host" name="host" type="text" class="mt-1 block w-full" :value="$value('host')" placeholder="mqtt atau broker.example.com" required />
                            <x-input-error :messages="$errors->get('host')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="port" value="Broker Port" />
                            <x-text-input id="port" name="port" type="number" min="1" max="65535" class="mt-1 block w-full" :value="$value('port')" required />
                            <x-input-error :messages="$errors->get('port')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="username" value="Username" />
                            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="$value('username')" autocomplete="off" />
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password" value="Password" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" value="" autocomplete="new-password" placeholder="{{ $setting?->password ? 'Tersimpan — kosongkan untuk mempertahankan' : 'Masukkan password broker' }}" />
                            <p class="mt-1.5 text-xs text-gray-400">Password terenkripsi dan tidak ditampilkan kembali.</p>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">TLS & Certificate</h2>
                    <p class="mt-0.5 text-xs text-gray-400">Broker internet disarankan menggunakan TLS dengan verifikasi certificate aktif.</p>
                </div>
                <div class="space-y-4 p-6">
                    @foreach([
                        'tls_enabled' => ['Enable TLS', 'Umumnya menggunakan port 8883.'],
                        'tls_verify_peer' => ['Verify certificate authority', 'Pastikan certificate broker diterbitkan CA yang dipercaya.'],
                        'tls_verify_peer_name' => ['Verify broker hostname', 'Hostname certificate harus sama dengan Broker Host.'],
                        'tls_allow_self_signed' => ['Allow self-signed certificate', 'Hanya untuk jaringan privat yang dikelola sendiri.'],
                    ] as $field => [$label, $help])
                        <label class="flex items-start justify-between gap-5 rounded-xl border border-gray-100 p-4">
                            <span>
                                <span class="block text-sm font-semibold text-gray-800">{{ $label }}</span>
                                <span class="mt-0.5 block text-xs text-gray-400">{{ $help }}</span>
                            </span>
                            <input type="hidden" name="{{ $field }}" value="0">
                            <input type="checkbox" name="{{ $field }}" value="1" @checked((bool) old($field, $value($field))) class="mt-1 h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        </label>
                    @endforeach

                    <div>
                        <x-input-label for="tls_ca_file" value="Custom CA File (optional)" />
                        <x-text-input id="tls_ca_file" name="tls_ca_file" type="text" class="mt-1 block w-full" :value="$value('tls_ca_file')" placeholder="/run/secrets/hydroflow/mqtt-ca.crt" />
                        <p class="mt-1.5 text-xs text-gray-400">Path harus tersedia di dalam container aplikasi. Kosongkan untuk memakai CA bawaan sistem.</p>
                        <x-input-error :messages="$errors->get('tls_ca_file')" class="mt-2" />
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">Connection Behaviour</h2>
                </div>
                <div class="grid grid-cols-1 gap-5 p-6 sm:grid-cols-3">
                    <div>
                        <x-input-label for="connect_timeout" value="Connect Timeout (s)" />
                        <x-text-input id="connect_timeout" name="connect_timeout" type="number" min="1" max="60" class="mt-1 block w-full" :value="$value('connect_timeout')" required />
                        <x-input-error :messages="$errors->get('connect_timeout')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="socket_timeout" value="Socket Timeout (s)" />
                        <x-text-input id="socket_timeout" name="socket_timeout" type="number" min="1" max="120" class="mt-1 block w-full" :value="$value('socket_timeout')" required />
                        <x-input-error :messages="$errors->get('socket_timeout')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="keep_alive" value="Keep Alive (s)" />
                        <x-text-input id="keep_alive" name="keep_alive" type="number" min="5" max="3600" class="mt-1 block w-full" :value="$value('keep_alive')" required />
                        <x-input-error :messages="$errors->get('keep_alive')" class="mt-2" />
                    </div>
                    <label class="flex items-center gap-3 sm:col-span-3">
                        <input type="hidden" name="clean_session" value="0">
                        <input type="checkbox" name="clean_session" value="1" @checked((bool) old('clean_session', $value('clean_session'))) class="h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span>
                            <span class="block text-sm font-semibold text-gray-800">Clean session</span>
                            <span class="block text-xs text-gray-400">Direkomendasikan untuk koneksi publish command yang singkat.</span>
                        </span>
                    </label>
                </div>
            </section>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="submit" name="intent" value="test" class="inline-flex items-center justify-center rounded-xl border border-blue-200 bg-white px-5 py-2.5 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                    Test Connection
                </button>
                <button type="submit" name="intent" value="save" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700">
                    Save Configuration
                </button>
            </div>
        </form>

        <aside class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Active Source</h2>
                <div class="mt-4 flex items-center gap-3">
                    <span class="h-3 w-3 rounded-full {{ $resolved['source'] === 'database' ? 'bg-emerald-500' : 'bg-amber-400' }}"></span>
                    <div>
                        <p class="text-sm font-bold text-gray-800">{{ $resolved['source'] === 'database' ? 'Web Configuration' : 'Environment (.env)' }}</p>
                        <p class="text-xs text-gray-400">{{ $resolved['host'] }}:{{ $resolved['port'] }}</p>
                    </div>
                </div>
                @if($setting?->updatedBy)
                    <p class="mt-4 border-t border-gray-100 pt-4 text-xs leading-relaxed text-gray-400">
                        Terakhir diubah oleh {{ $setting->updatedBy->name }}<br>{{ $setting->updated_at?->format('d M Y, H:i:s') }}
                    </p>
                @endif
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-xs leading-relaxed text-amber-900">
                <p class="font-bold">Perhatian</p>
                <p class="mt-2">Mengganti broker memengaruhi penerimaan data seluruh gateway. Uji koneksi sebelum menyimpan dan pastikan gateway memakai broker yang sama.</p>
            </div>
        </aside>
    </div>
</x-app-layout>
