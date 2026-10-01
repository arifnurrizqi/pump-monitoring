<x-app-layout>
    {{-- $myRole is passed directly from DeviceController::show() --}}

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ $backUrl }}"
               class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 leading-tight">{{ $device->device_tag }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ $device->location ?? 'No location set' }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('devices.monitoring.index', $device) }}"
               class="inline-flex items-center px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50">Sumber data</a>
            <a href="{{ route('devices.settings.edit', $device) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl
                      text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                </svg>
                Settings
            </a>
            <a href="{{ route('devices.edit', $device) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700
                      border border-transparent rounded-xl text-sm font-semibold text-white transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582
                             16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1
                             1.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0
                             0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0
                             1 5.25 6H10"/>
                </svg>
                Edit
            </a>
        </div>
    </div>

    <div class="space-y-4"
         data-realtime-device-id="{{ $device->id }}"
         data-status="{{ $device->connection_status }}"
         data-last-seen-at="{{ $device->last_seen_at && (!$device->latestData?->last_seen_at || $device->last_seen_at->greaterThan($device->latestData->last_seen_at)) ? $device->last_seen_at->toIso8601String() : $device->latestData?->last_seen_at?->toIso8601String() }}"
         data-send-interval="{{ $device->setting?->send_interval_sec ?? 10 }}"
         data-usage-today="{{ $usageTodayLiter }}"
         data-usage-month="{{ $usageThisMonthLiter }}"
         data-active-alarm-count="{{ $device->activeAlarms->count() }}">

        {{-- ── Row 1: Device Info + Latest Telemetry ────────────── --}}
        <div class="grid md:grid-cols-2 gap-4">

            {{-- Device Info --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Device Info</h3>
                </div>
                <div class="px-5 py-4 space-y-3">
                    @php
                        $cs = $device->connection_status;
                        $statusBadge = match($cs) {
                            'online'  => 'bg-emerald-100 text-emerald-700',
                            'pending' => 'bg-amber-100 text-amber-700',
                            default   => 'bg-gray-100 text-gray-600',
                        };
                    @endphp

                    @foreach([
                        'Device Tag'  => $device->device_tag,
                        'Serial No.'  => $device->device_sn,
                        'Location'    => $device->location ?? '—',
                        'Description' => $device->description ?? '—',
                    ] as $label => $value)
                    <div class="flex items-start justify-between gap-4 py-1.5 border-b border-gray-50 last:border-0">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide whitespace-nowrap">{{ $label }}</span>
                        <span class="text-sm text-gray-800 text-right">{{ $value }}</span>
                    </div>
                    @endforeach

                    <div class="flex items-center justify-between gap-4 py-1.5">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Status</span>
                        <span data-realtime-field="connection-status" data-status="{{ $cs }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full {{ $statusBadge }}">
                            <span data-realtime-status-label>{{ ucfirst($cs) }}</span>
                        </span>
                    </div>

                    @if($device->latestData?->last_seen_at)
                    <div class="flex items-center justify-between gap-4 py-1.5">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Last Seen</span>
                        <span data-realtime-field="last-seen" class="text-sm text-gray-500">
                            {{ $device->latestData->last_seen_at->format('d M Y, H:i:s') }}
                        </span>
                    </div>
                    @endif

                    <div class="flex items-center justify-between gap-4 border-t border-gray-50 py-1.5">
                        <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Alarm Active</span>
                        @if($device->activeAlarms->isNotEmpty())
                            <span data-realtime-field="alarm-status" class="inline-flex items-center gap-2 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700">
                                <span class="relative flex h-2 w-2">
                                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                                    <span class="relative inline-flex h-2 w-2 rounded-full bg-red-600"></span>
                                </span>
                                {{ $device->activeAlarms->count() }} active
                            </span>
                        @else
                            <span data-realtime-field="alarm-status" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                No active alarm
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Latest Telemetry --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Latest Telemetry</h3>
                </div>
                @if($device->latestData)
                    @php
                        $latest = $device->latestData;
                        $setting = $device->setting;

                        // Check pH
                        $ph = $latest->ph;
                        $phRed = false;
                        if ($ph !== null && $setting) {
                            if ($setting->ph_min !== null && $ph < $setting->ph_min) $phRed = true;
                            if ($setting->ph_max !== null && $ph > $setting->ph_max) $phRed = true;
                        }

                        // Check Turbidity
                        $turb = $latest->turbidity;
                        $turbRed = false;
                        if ($turb !== null && $setting && $setting->turbidity_max !== null && $turb > $setting->turbidity_max) {
                            $turbRed = true;
                        }

                        // Check sample flow
                        $sampleFlow = $latest->sample_flow;
                        $sampleFlowRed = false;
                        if ($sampleFlow !== null && $setting) {
                            if ($setting->sample_flow_min !== null && $sampleFlow < $setting->sample_flow_min) $sampleFlowRed = true;
                            if ($setting->sample_flow_max !== null && $sampleFlow > $setting->sample_flow_max) $sampleFlowRed = true;
                        }

                        // Check Level
                        $lvl = $latest->level_percent;
                        $levelRed = false;
                        if ($lvl !== null && $setting) {
                            if ($setting->level_min_percent !== null && $lvl < $setting->level_min_percent) $levelRed = true;
                            if ($setting->level_max_percent !== null && $lvl > $setting->level_max_percent) $levelRed = true;
                        }

                        // Check MCU Voltage
                        $vMcu = $latest->device_voltage_mcu;
                        $vMcuRed = false;
                        if ($vMcu !== null && $setting && $setting->device_voltage_mcu_min !== null && $vMcu < $setting->device_voltage_mcu_min) {
                            $vMcuRed = true;
                        }

                        // Check 5V Voltage
                        $v5v = $latest->device_voltage_5v;
                        $v5vRed = false;
                        if ($v5v !== null && $setting && $setting->device_voltage_5v_min !== null && $v5v < $setting->device_voltage_5v_min) {
                            $v5vRed = true;
                        }

                        // Check Input Voltage
                        $vIn = $latest->device_voltage_input;
                        $vInRed = false;
                        if ($vIn !== null && $setting && $setting->device_voltage_input_min !== null && $vIn < $setting->device_voltage_input_min) {
                            $vInRed = true;
                        }

                        $telemetryItems = [
                            [
                                'key'   => 'ph',
                                'label' => 'pH',
                                'value' => $ph,
                                'red'   => $phRed,
                            ],
                            [
                                'key'   => 'turbidity',
                                'label' => 'Turbidity',
                                'value' => $turb !== null ? $turb . ' NTU' : null,
                                'red'   => $turbRed,
                            ],
                            [
                                'key'   => 'sample-flow',
                                'label' => 'Sample Flow',
                                'value' => $sampleFlow !== null ? $sampleFlow . ' L/min' : null,
                                'red'   => $sampleFlowRed,
                            ],
                            [
                                'key'   => 'delta-pulse',
                                'label' => 'Delta Pulse',
                                'value' => $latest->delta_pulse !== null ? number_format((int) $latest->delta_pulse) . ' pulse' : null,
                                'red'   => false,
                            ],
                            [
                                'key'   => 'total-pulse',
                                'label' => 'Total Pulse',
                                'value' => $device->latestData?->total_pulse !== null ? number_format((int) $device->latestData->total_pulse) . ' pulse' : null,
                                'red'   => false,
                            ],
                            [
                                'key'   => 'usage-today',
                                'label' => 'Usage Today',
                                'value' => number_format($usageTodayLiter, 2) . ' L',
                                'red'   => false,
                            ],
                            [
                                'key'   => 'usage-month-liter',
                                'label' => 'Usage This Month',
                                'value' => number_format($usageThisMonthLiter, 2) . ' L',
                                'red'   => false,
                            ],
                            [
                                'key'   => 'level-percent',
                                'label' => 'Level %',
                                'value' => $lvl !== null ? $lvl . '%' : null,
                                'red'   => $levelRed,
                            ],
                            [
                                'key'   => 'level-distance-cm',
                                'label' => 'Sensor Distance',
                                'value' => $latest->level_distance_cm !== null ? $latest->level_distance_cm . ' cm' : null,
                                'red'   => false,
                            ],
                            [
                                'key'   => 'level-cm',
                                'label' => 'Water Height',
                                'value' => $latest->level_cm !== null ? $latest->level_cm . ' cm' : null,
                                'red'   => $levelRed,
                            ],
                            [
                                'key'   => 'voltage-mcu',
                                'label' => 'MCU Voltage',
                                'value' => $vMcu !== null ? $vMcu . ' V' : null,
                                'red'   => $vMcuRed,
                            ],
                            [
                                'key'   => 'voltage-5v',
                                'label' => '5V Rail',
                                'value' => $v5v !== null ? $v5v . ' V' : null,
                                'red'   => $v5vRed,
                            ],
                            [
                                'key'   => 'voltage-input',
                                'label' => 'Input Voltage',
                                'value' => $vIn !== null ? $vIn . ' V' : null,
                                'red'   => $vInRed,
                            ],
                        ];
                    @endphp
                    <div class="grid grid-cols-2 gap-3 px-5 py-4 sm:grid-cols-3">
                        @foreach($telemetryItems as $item)
                        <div class="bg-gray-50 rounded-xl px-3 py-2.5 border border-gray-100">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5">{{ $item['label'] }}</p>
                            <p data-realtime-field="{{ $item['key'] }}" class="text-sm font-bold font-mono {{ $item['red'] ? 'text-red-600' : 'text-gray-800' }}">{{ $item['value'] ?? '—' }}</p>
                        </div>
                        @endforeach
                    </div>
                    <div class="px-5 pb-4">
                        <p class="text-xs text-gray-400">
                            Recorded: <span data-realtime-field="recorded-at">{{ $device->latestData->recorded_at?->format('d M Y, H:i:s') ?? '—' }}</span>
                        </p>
                    </div>
                @else
                    <div class="px-5 py-10 text-center text-gray-400 text-sm">No telemetry data yet.</div>
                @endif
            </div>

        </div>

        {{-- ══════════════════════════════════════════════════════════
             TELEMETRY CHARTS
        ══════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm"
             x-data="{ period: '24h' }"
             x-init="$watch('period', p => typeof window.updateCharts === 'function' && window.updateCharts(p))">

            {{-- Card header + period switcher --}}
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800">Telemetry Charts</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Aggregated sensor history</p>
                </div>
                {{-- Period tabs --}}
                <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl">
                    @foreach(['1h' => '1H', '7h' => '7H', '24h' => '24H', '7d' => '7D', '30d' => '30D'] as $pKey => $pLabel)
                    <button @click="period = '{{ $pKey }}'"
                            :class="period === '{{ $pKey }}'
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-500 hover:text-gray-700'"
                            class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-all">
                        {{ $pLabel }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Colour legend --}}
            <div class="px-5 pt-3 pb-1 flex flex-wrap items-center gap-x-5 gap-y-1 text-xs text-gray-500">
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-0.5 rounded" style="background:#6366f1"></span> pH
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-0.5 rounded" style="background:#8b5cf6"></span> Turbidity
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-0.5 rounded" style="background:#0ea5e9"></span> Level
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-3 h-0.5 rounded" style="background:#10b981"></span> Water Usage
                </span>
                <span class="flex items-center gap-1.5 ml-2 pl-2 border-l border-gray-200">
                    <svg width="16" height="6" viewBox="0 0 16 6">
                        <line x1="0" y1="3" x2="16" y2="3" stroke="rgba(239,68,68,0.7)" stroke-width="1.5"
                              stroke-dasharray="4 3"/>
                    </svg>
                    Threshold
                </span>
            </div>

            {{-- No-data state --}}
            <div id="charts-no-data" class="hidden py-14 px-5 text-center">
                <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5
                                 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75zM9.75 8.625c0-.621.504-1.125
                                 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125
                                 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125
                                 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125
                                 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125z"/>
                    </svg>
                </div>
                <p class="text-sm font-medium text-gray-700">No data for this period</p>
                <p class="text-xs text-gray-400 mt-1">Aggregated records will appear here once telemetry is collected.</p>
            </div>

            {{-- 2 × 2 chart grid --}}
            <div id="charts-grid" class="p-5 grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- pH Level --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:#6366f1"></span>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">pH Level</p>
                    </div>
                    <div class="h-44"><canvas id="chart-ph"></canvas></div>
                </div>

                {{-- Turbidity --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:#8b5cf6"></span>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Turbidity (NTU)</p>
                    </div>
                    <div class="h-44"><canvas id="chart-turbidity"></canvas></div>
                </div>

                {{-- Water Level % --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:#0ea5e9"></span>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Water Level (%)</p>
                    </div>
                    <div class="h-44"><canvas id="chart-level"></canvas></div>
                </div>

                {{-- Water Usage --}}
                <div class="space-y-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full shrink-0" style="background:#10b981"></span>
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Water Usage (L)</p>
                    </div>
                    <div class="h-44"><canvas id="chart-water-usage"></canvas></div>
                </div>

            </div>
        </div>

        @if($device->sources->isNotEmpty())
        <section class="bg-white rounded-2xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div><h2 class="text-sm font-semibold text-gray-800">Monitoring sumber data</h2><p class="text-xs text-gray-500 mt-1">Nilai terbaru dari gateway; histori diringkas per interval.</p></div>
                <a href="{{ route('devices.monitoring.index', $device) }}" class="text-xs text-blue-600">Konfigurasi &rarr;</a>
            </div>
            <div class="p-5 space-y-5">
                @foreach($device->sources as $source)
                <div data-source-id="{{ $source->id }}">
                    <div class="flex items-baseline gap-2 mb-3"><h3 class="font-semibold text-gray-800">{{ $source->name }}</h3><span class="text-xs text-gray-400">{{ $source->asset?->name ?? $source->source_key }}</span></div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                        @foreach($source->parameters as $parameter)
                        @php($reading = $sourceReadings[$source->id][$parameter->key] ?? null)
                        <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-3" data-source-parameter="{{ $parameter->key }}">
                            <p class="text-xs text-gray-500">{{ $parameter->label }}</p>
                            <p class="font-semibold text-gray-900 mt-1"><span data-parameter-value>{{ $reading && $reading['value'] !== null ? number_format($reading['value'], 2, ',', '.') : '—' }}</span> <span class="text-xs font-normal text-gray-500">{{ $parameter->unit }}</span></p>
                            <p class="text-xs text-gray-400 mt-1" data-parameter-quality>{{ $reading['quality'] ?? 'belum ada data' }}</p>
                            <p class="text-xs text-gray-400" data-parameter-time>{{ $reading['recorded_at'] ?? '' }}</p>
                        </div>
                        @endforeach
                    </div>
                    @if($sourceHistory[$source->id]->isNotEmpty())
                    <details class="mt-3"><summary class="text-xs text-blue-600 cursor-pointer">Lihat 10 interval terakhir</summary>
                        <div class="overflow-x-auto mt-2"><table class="min-w-full text-xs text-left"><thead><tr class="bg-gray-50 text-gray-500"><th class="p-2">Waktu</th><th class="p-2">Sampel</th>@foreach($source->parameters as $parameter)<th class="p-2">{{ $parameter->label }} (rata-rata)</th>@endforeach</tr></thead><tbody>
                        @foreach($sourceHistory[$source->id] as $interval)
                        <tr class="border-t"><td class="p-2 whitespace-nowrap">{{ $interval->bucket_start?->format('d M H:i') }}</td><td class="p-2">{{ $interval->sample_count }}</td>
                            @foreach($source->parameters as $parameter)
                            @php($metric = $interval->metrics[$parameter->key] ?? null)
                            <td class="p-2">{{ $metric && ($metric['count'] ?? 0) > 0 ? number_format($metric['sum'] / $metric['count'], 2, ',', '.') : '—' }}</td>
                            @endforeach
                        </tr>
                        @endforeach
                        </tbody></table></div>
                    </details>
                    @endif
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- ── Device Settings summary ───────────────────────────── --}}
        @if($device->setting)
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Device Settings</h3>
                <a href="{{ route('devices.settings.edit', $device) }}"
                   class="text-xs text-blue-600 hover:text-blue-800 font-medium">Settings →</a>
            </div>
            <div class="px-5 py-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-3">
                @foreach([
                    'Sensor to Tank Bottom (H₀)' => ($device->setting->tank_height_cm ?? '—') . ' cm',
                    'Tank Volume'    => ($device->setting->tank_volume_liter ?? '—') . ' L',
                    'Pulse Per Liter'=> $device->setting->pulse_per_liter ?? '—',
                    'pH Min'         => $device->setting->ph_min ?? '—',
                    'pH Max'         => $device->setting->ph_max ?? '—',
                    'Turbidity Max'  => ($device->setting->turbidity_max ?? '—') . ' NTU',
                    'Sample Flow Min (L/min)' => $device->setting->sample_flow_min ?? '—',
                    'Sample Flow Max (L/min)' => $device->setting->sample_flow_max ?? '—',
                    'Level Min %'    => ($device->setting->level_min_percent ?? '—') . '%',
                    'Level Max %'    => ($device->setting->level_max_percent ?? '—') . '%',
                    'Alarm'          => $device->setting->alarm_enabled ? 'Enabled' : 'Disabled',
                    'Sampling'       => ($device->setting->sampling_interval_sec ?? '—') . 's',
                    'Send Interval'  => ($device->setting->send_interval_sec ?? '—') . 's',
                ] as $label => $value)
                <div class="bg-gray-50 rounded-xl px-3 py-2.5 border border-gray-100">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-0.5 truncate">{{ $label }}</p>
                    <p class="text-sm font-semibold text-gray-800">{{ $value }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- ── Active Alarms ─────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Active Alarms</h3>
                @if($device->activeAlarms->count())
                    <span class="px-2 py-0.5 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                        {{ $device->activeAlarms->count() }}
                    </span>
                @endif
            </div>
            @if($device->activeAlarms->count())
                <div class="px-5 py-4 space-y-2">
                    @foreach($device->activeAlarms as $alarm)
                        <div class="flex items-start gap-3 rounded-xl bg-red-50 border border-red-100 px-4 py-3">
                            <div class="w-2 h-2 mt-1.5 rounded-full bg-red-500 shrink-0"></div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-red-800">{{ $alarm->alarm_code }}</p>
                                <p class="text-xs text-red-600 mt-0.5">{{ $alarm->alarm_message }}</p>
                                <p class="text-xs text-gray-400 mt-1">
                                    Since {{ $alarm->started_at?->format('d M Y, H:i:s') ?? '—' }}
                                </p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-5 py-8 text-center">
                    <div class="w-8 h-8 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-2">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-500">No active alarms</p>
                </div>
            @endif
        </div>

        {{-- ── User Access ────────────────────────────────────────── --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-800">User Access</h3>
            </div>

            <div class="px-5 py-5 space-y-5">

                {{-- Share form (owner only) --}}
                @if($myRole === 'owner')
                <form action="{{ route('devices.share', $device) }}" method="POST"
                      class="grid gap-4 sm:grid-cols-3 pb-5 border-b border-gray-100">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="email" :value="__('User Email')" />
                        <x-text-input
                            type="email" name="email" id="email"
                            :value="old('email')" placeholder="user@email.com"
                        />
                        @error('email')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <x-input-label for="role" :value="__('Role')" />
                        <select name="role" id="role"
                                class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm
                                       text-gray-900 focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors">
                            <option value="viewer"   {{ old('role') === 'viewer'   ? 'selected' : '' }}>Viewer</option>
                            <option value="operator" {{ old('role') === 'operator' ? 'selected' : '' }}>Operator</option>
                        </select>
                        @error('role')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                    <div class="sm:col-span-3">
                        <x-primary-button type="submit">Share Access</x-primary-button>
                    </div>
                </form>
                @endif

                {{-- Access list --}}
                @if($device->users->count())
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-4 py-2.5">Name</th>
                                <th class="px-4 py-2.5">Email</th>
                                <th class="px-4 py-2.5">Role</th>
                                @if($myRole === 'owner')
                                    <th class="px-4 py-2.5">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($device->users as $user)
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-4 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                                    <td class="px-4 py-3 text-gray-500">{{ $user->email }}</td>
                                    <td class="px-4 py-3">
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-50 text-blue-700">
                                            {{ ucfirst($user->pivot->role) }}
                                        </span>
                                    </td>
                                    @if($myRole === 'owner')
                                    <td class="px-4 py-3">
                                        @if($user->pivot->role !== 'owner')
                                            <div class="flex items-center gap-2">
                                                <form action="{{ route('devices.access.update', [$device, $user]) }}"
                                                      method="POST" class="flex items-center gap-2">
                                                    @csrf @method('PATCH')
                                                    <select name="role"
                                                            class="rounded-lg border border-gray-200 bg-gray-50 px-2.5 py-1.5 text-xs
                                                                   text-gray-900 focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors">
                                                        <option value="viewer"   {{ $user->pivot->role === 'viewer'   ? 'selected' : '' }}>Viewer</option>
                                                        <option value="operator" {{ $user->pivot->role === 'operator' ? 'selected' : '' }}>Operator</option>
                                                    </select>
                                                    <button type="submit"
                                                            class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700
                                                                   text-xs font-semibold rounded-lg transition-colors">
                                                        Update
                                                    </button>
                                                </form>
                                                <form action="{{ route('devices.access.remove', [$device, $user]) }}"
                                                      method="POST"
                                                      onsubmit="return confirm('Remove access for {{ $user->name }}?')">
                                                    @csrf @method('DELETE')
                                                    <button type="submit"
                                                            class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600
                                                                   text-xs font-semibold rounded-lg transition-colors">
                                                        Remove
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">Owner</span>
                                        @endif
                                    </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <p class="text-sm text-gray-400">No users have access to this device yet.</p>
                @endif

            </div>
        </div>

    </div>

    {{-- ── Chart data + script (only on this page) ─────────────── --}}
    <script>
        window.__DEVICE_CHART_DATA__ = @json($chartData);
    </script>
    @push('scripts')
        @vite('resources/js/device-show.js')
    @endpush

</x-app-layout>
