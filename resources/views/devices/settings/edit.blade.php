<x-app-layout>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ $backUrl }}"
           class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight">Device Settings</h1>
            <p class="text-sm text-gray-500 mt-1">{{ $device->device_tag }}</p>
        </div>
    </div>

    <form action="{{ route('devices.settings.update', $device) }}" method="POST" class="space-y-6 w-full max-w-none">
        @csrf
        @method('PUT')

        {{-- Flash --}}
        @if(session('success'))
            <div class="flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
                <svg class="w-4 h-4 shrink-0 fill-emerald-500" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                          d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483
                             4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z"/>
                </svg>
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
            {{-- ──────────────────────────────────────────────────────
                 SECTION 1 — Device Configuration
            ────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Device Configuration</h3>
                    <p class="text-xs text-gray-400 mt-0.5">General settings and operational parameters.</p>
                </div>

                <div class="px-6 py-6 space-y-8">

                    {{-- Tank Information --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Level Tank calibration</h4>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="tank_height_cm" :value="__('Sensor to Tank Bottom / H₀ (cm)')" />
                                <x-text-input type="number" step="0.01" name="tank_height_cm" id="tank_height_cm"
                                    :value="old('tank_height_cm', $device->setting->tank_height_cm ?? '')" placeholder="e.g. 200"/>
                                <p class="mt-1.5 text-xs leading-relaxed text-gray-400">Ukur vertikal dari muka sensor ultrasonik sampai dasar bagian dalam tandon.</p>
                                @error('tank_height_cm') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="tank_volume_liter" :value="__('Tank Volume (Liter)')" />
                                <x-text-input type="number" step="0.01" name="tank_volume_liter" id="tank_volume_liter"
                                    :value="old('tank_volume_liter', $device->setting->tank_volume_liter ?? '')" placeholder="e.g. 5000"/>
                                @error('tank_volume_liter') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Usage Flow Calibration --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Usage Flow Calibration</h4>
                        <x-input-label for="pulse_per_liter" :value="__('Pulse per Liter')" />
                        <x-text-input type="number" step="0.01" name="pulse_per_liter" id="pulse_per_liter"
                            :value="old('pulse_per_liter', $device->setting->pulse_per_liter ?? '')" placeholder="e.g. 450"/>
                        @error('pulse_per_liter') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Device Operation --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Device Operation</h4>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <x-input-label for="sampling_interval_sec" :value="__('Sampling Interval (sec)')" />
                                <x-text-input type="number" name="sampling_interval_sec" id="sampling_interval_sec"
                                    :value="old('sampling_interval_sec', $device->setting->sampling_interval_sec ?? '')" placeholder="e.g. 60"/>
                                @error('sampling_interval_sec') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="send_interval_sec" :value="__('Send Interval (sec)')" />
                                <x-text-input type="number" name="send_interval_sec" id="send_interval_sec"
                                    :value="old('send_interval_sec', $device->setting->send_interval_sec ?? '')" placeholder="e.g. 300"/>
                                @error('send_interval_sec') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="timezone" :value="__('Timezone')" />
                                <x-text-input type="text" name="timezone" id="timezone"
                                    :value="old('timezone', $device->setting->timezone ?? 'Asia/Jakarta')" placeholder="Asia/Jakarta"/>
                                @error('timezone') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Notes</h4>
                        <x-input-label for="notes" :value="__('Additional Notes')" />
                        <textarea name="notes" id="notes" rows="3" placeholder="Optional notes…"
                                  class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm
                                         text-gray-900 shadow-sm focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors"
                        >{{ old('notes', $device->setting->notes ?? '') }}</textarea>
                        @error('notes') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                </div>
            </div>

            {{-- ──────────────────────────────────────────────────────
                 SECTION 2 — Alarm Threshold
            ────────────────────────────────────────────────────── --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-900">Alarm Thresholds</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Parameter limits for warning and abnormal conditions.</p>
                </div>

                <div class="px-6 py-6 space-y-8">

                    {{-- Enable alarm toggle --}}
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" name="alarm_enabled" id="alarm_enabled" value="1"
                               {{ old('alarm_enabled', $device->setting->alarm_enabled ?? true) ? 'checked' : '' }}
                               class="w-4 h-4 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                        <div>
                            <p class="text-sm font-semibold text-gray-700">Enable Alarm</p>
                            <p class="text-xs text-gray-400">Trigger alarms when parameters exceed thresholds</p>
                        </div>
                    </label>
                    @error('alarm_enabled') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    {{-- Water Quality --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Water Quality</h4>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <x-input-label for="ph_min" :value="__('pH Min')" />
                                <x-text-input type="number" step="0.01" name="ph_min" id="ph_min"
                                    :value="old('ph_min', $device->setting->ph_min ?? '')" placeholder="e.g. 6.5"/>
                                @error('ph_min') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="ph_max" :value="__('pH Max')" />
                                <x-text-input type="number" step="0.01" name="ph_max" id="ph_max"
                                    :value="old('ph_max', $device->setting->ph_max ?? '')" placeholder="e.g. 8.5"/>
                                @error('ph_max') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="turbidity_max" :value="__('Turbidity Max (NTU)')" />
                                <x-text-input type="number" step="0.01" name="turbidity_max" id="turbidity_max"
                                    :value="old('turbidity_max', $device->setting->turbidity_max ?? '')" placeholder="e.g. 5"/>
                                @error('turbidity_max') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Sample Flow & Level --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Sample Flow &amp; Level</h4>
                        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                            <div>
                                <x-input-label for="sample_flow_min" :value="__('Sample Flow Min (L/min)')" />
                                <x-text-input type="number" step="0.01" name="sample_flow_min" id="sample_flow_min"
                                    :value="old('sample_flow_min', $device->setting->sample_flow_min ?? '')"/>
                                @error('sample_flow_min') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="sample_flow_max" :value="__('Sample Flow Max (L/min)')" />
                                <x-text-input type="number" step="0.01" name="sample_flow_max" id="sample_flow_max"
                                    :value="old('sample_flow_max', $device->setting->sample_flow_max ?? '')"/>
                                @error('sample_flow_max') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="level_min_percent" :value="__('Level Min (%)')" />
                                <x-text-input type="number" step="0.01" name="level_min_percent" id="level_min_percent"
                                    :value="old('level_min_percent', $device->setting->level_min_percent ?? '')" placeholder="e.g. 10"/>
                                @error('level_min_percent') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="level_max_percent" :value="__('Level Max (%)')" />
                                <x-text-input type="number" step="0.01" name="level_max_percent" id="level_max_percent"
                                    :value="old('level_max_percent', $device->setting->level_max_percent ?? '')" placeholder="e.g. 90"/>
                                @error('level_max_percent') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Device Voltage --}}
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-4">Device Voltage</h4>
                        <div class="grid gap-5 sm:grid-cols-3">
                            <div>
                                <x-input-label for="device_voltage_mcu_min" :value="__('MCU Voltage Min (V)')" />
                                <x-text-input type="number" step="0.01" name="device_voltage_mcu_min" id="device_voltage_mcu_min"
                                    :value="old('device_voltage_mcu_min', $device->setting->device_voltage_mcu_min ?? '')" placeholder="e.g. 3.0"/>
                                @error('device_voltage_mcu_min') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="device_voltage_5v_min" :value="__('5V Rail Min (V)')" />
                                <x-text-input type="number" step="0.01" name="device_voltage_5v_min" id="device_voltage_5v_min"
                                    :value="old('device_voltage_5v_min', $device->setting->device_voltage_5v_min ?? '')" placeholder="e.g. 4.5"/>
                                @error('device_voltage_5v_min') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <x-input-label for="device_voltage_input_min" :value="__('Input Voltage Min (V)')" />
                                <x-text-input type="number" step="0.01" name="device_voltage_input_min" id="device_voltage_input_min"
                                    :value="old('device_voltage_input_min', $device->setting->device_voltage_input_min ?? '')" placeholder="e.g. 10"/>
                                @error('device_voltage_input_min') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        {{-- Sticky actions --}}
        <div class="flex items-center gap-3 pt-2">
            <x-primary-button type="submit">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-4 16H11m2-4H11m4-4H9"/>
                </svg>
                Save Settings
            </x-primary-button>
            <x-secondary-button type="button" onclick="window.location='{{ $backUrl }}'">
                Cancel
            </x-secondary-button>
        </div>

    </form>

</x-app-layout>
