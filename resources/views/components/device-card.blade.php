@props(['device'])

@php
    // Status styling
    $statusConfig = match($device['status'] ?? 'offline') {
        'online'  => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Online'],
        'alarm'   => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-500',     'label' => 'Alarm'],
        'pending' => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'dot' => 'bg-amber-400',   'label' => 'Pending'],
        default   => ['bg' => 'bg-gray-100',    'text' => 'text-gray-600',    'dot' => 'bg-gray-400',    'label' => 'Offline'],
    };

    // Level bar geometry (sight glass: y=43 → y=208, height=165px)
    $level      = max(0, min(100, (int)($device['level'] ?? 0)));
    $barHeight  = (int)round($level * 165 / 100);
    $barY       = 208 - $barHeight;

    // Unique SVG gradient ID namespace to avoid conflicts between multiple cards
    $uid = 'sv' . $device->id;

    // Check thresholds
    $ph = $device['ph'] ?? null;
    $phMin = $device->setting->ph_min ?? null;
    $phMax = $device->setting->ph_max ?? null;
    $phOutOfRange = false;
    if ($ph !== null) {
        if ($phMin !== null && $ph < $phMin) $phOutOfRange = true;
        if ($phMax !== null && $ph > $phMax) $phOutOfRange = true;
    }
            
    $turbidity = $device['turbidity'] ?? null;
    $turbidityMax = $device->setting->turbidity_max ?? null;
    $turbidityOutOfRange = false;
    if ($turbidity !== null && $turbidityMax !== null && $turbidity > $turbidityMax) {
        $turbidityOutOfRange = true;
    }

    $levelMin = $device->setting->level_min_percent ?? null;
    $levelMax = $device->setting->level_max_percent ?? null;
    $levelOutOfRange = false;
    if ($device['level'] !== null) {
        if ($levelMin !== null && $level < $levelMin) $levelOutOfRange = true;
        if ($levelMax !== null && $level > $levelMax) $levelOutOfRange = true;
    }

    $valve = $device->latestData?->valve_state;
    $command = $device->latestCommand ?? null;
    // @dd($device->toArray())
@endphp

<div class="device-card bg-white rounded-2xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200 p-5 flex flex-col gap-4"
     data-realtime-device-id="{{ $device->id }}"
     data-status="{{ $device->connection_status }}"
     data-last-seen-at="{{ $device->last_seen_at && (!$device->latestData?->last_seen_at || $device->last_seen_at->greaterThan($device->latestData->last_seen_at)) ? $device->last_seen_at->toIso8601String() : $device->latestData?->last_seen_at?->toIso8601String() }}"
     data-send-interval="{{ $device->setting?->send_interval_sec ?? 10 }}"
     data-usage-today="{{ (float) $device->usage_today }}"
     data-usage-month="{{ (float) $device->usage_month * 1000 }}"
     data-active-alarm-count="{{ (int) ($device['alarm_count'] ?? 0) }}">

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-bold text-gray-900 uppercase leading-tight truncate">
                {{ $device->location ?? '—' }}
            </h2>
            <p class="text-xs text-gray-400 capitalize mt-0.5">{{ $device->device_tag }}</p>
        </div>

        <div class="shrink-0 text-right space-y-0.5">
            {{-- Status badge --}}
            <span data-realtime-field="connection-status" data-status="{{ $device->connection_status }}"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold
                         {{ $statusConfig['bg'] }} {{ $statusConfig['text'] }}">
                <span data-realtime-status-dot class="w-1.5 h-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                <span data-realtime-status-label>{{ $statusConfig['label'] }}</span>
            </span>
            <p class="text-xs text-gray-400">Last update:
                <span data-realtime-field="last-update">{{ $device->last_update?->format('d M Y, H:i:s') ?? '—' }}</span>
            </p>
        </div>
    </div>

    {{-- ── Body: SVG Tank + Parameters ─────────────────────────── --}}
    @if(!$device->latestData && ($device->sources_count ?? 0) > 0)
    <div class="rounded-xl border border-blue-100 bg-blue-50 px-4 py-5 flex-1">
        <p class="text-xs font-semibold text-blue-700 uppercase">Monitoring gateway</p>
        <p class="text-xl font-bold text-gray-900 mt-2">{{ $device->sources_count }} sumber data</p>
        <p class="text-sm text-gray-600 mt-1">Nilai sensor dan alarm tersedia di detail device.</p>
    </div>
    @else
    <div class="flex gap-4 items-start">

        {{-- Tank SVG --}}
        <div class="w-[148px] shrink-0 flex justify-center">
            <svg width="148" viewBox="0 0 157 297" xmlns="http://www.w3.org/2000/svg">

                <rect width="12.2717" height="59.0666"
                    transform="matrix(0.547911 0.836537 -0.870529 0.492118 72.4192 235)"
                    fill="url(#{{ $uid }}_p0)" stroke="#5E5E5E" stroke-width="1"/>

                <rect width="12.2717" height="59.0666"
                    transform="matrix(0.455856 -0.890054 0.858388 0.513001 78 243.922)"
                    fill="url(#{{ $uid }}_p1)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="1" y="40" width="155" height="171"
                    fill="url(#{{ $uid }}_p2)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="1" y="32" width="155" height="10"
                    fill="url(#{{ $uid }}_p3)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="19" y="232" width="16" height="58"
                    fill="url(#{{ $uid }}_p4)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="7" y="284" width="39" height="12"
                    fill="url(#{{ $uid }}_p5)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="123" y="232" width="15" height="58"
                    fill="url(#{{ $uid }}_p6)" stroke="#5E5E5E" stroke-width="1"/>

                <path d="M77.1327 250C110.542 250 156 237.338 156 219L1 219C1 236.465 43.7236 250 77.1327 250Z"
                    fill="url(#{{ $uid }}_p7)" stroke="#5E5E5E" stroke-width="1"/>

                <path d="M79.8673 1C46.4582 0.99986 1 13.662 1 32L156 32C156 14.5352 113.276 1.00014 79.8673 1Z"
                    fill="url(#{{ $uid }}_p8)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="114" y="284" width="36" height="12"
                    fill="url(#{{ $uid }}_p9)" stroke="#5E5E5E" stroke-width="1"/>

                <rect x="1" y="209" width="155" height="10"
                    fill="url(#{{ $uid }}_p10)" stroke="#5E5E5E" stroke-width="1"/>

                {{-- Sight glass (clear window) --}}
                <rect x="104" y="43" width="27" height="165" fill="#F9F9F9" stroke="#5E5E5E" stroke-width="1"/>

                {{-- Water level bar – fills from bottom up with easing animation and meniscus rounded corners --}}
                <rect data-realtime-field="level-fill" x="105" y="208" width="25" height="0" rx="1" ry="1" fill="url(#{{ $uid }}_waterGrad)">
                    <animate attributeName="height" from="0" to="{{ $barHeight }}" dur="1.8s" fill="freeze" calcMode="spline" keyTimes="0;1" keySplines="0.4 0 0.2 1"/>
                    <animate attributeName="y" from="208" to="{{ $barY }}" dur="1.8s" fill="freeze" calcMode="spline" keyTimes="0;1" keySplines="0.4 0 0.2 1"/>
                </rect>

                {{-- Sight glass tick marks --}}
                <line x1="105" y1="43.5"  x2="113" y2="43.5"  stroke="#FF0000"/>
                <line x1="105" y1="207.5" x2="113" y2="207.5" stroke="#FF0000"/>
                <line x1="105" y1="125.5" x2="113" y2="125.5" stroke="#FF0000"/>
                <line x1="105" y1="166.5" x2="113" y2="166.5" stroke="#FF0000"/>
                <line x1="105" y1="84.5"  x2="113" y2="84.5"  stroke="#FF0000"/>

                {{-- Animated Bubble highlights floating from bottom to water surface --}}
                @if($level > 0)
                <circle cx="112" cy="195" r="2.5" fill="rgba(255,255,255,0.7)" opacity="0">
                    <animate attributeName="cy" from="195" to="{{ max(45, $barY + 5) }}" dur="3.5s" repeatCount="indefinite" />
                    <animate attributeName="opacity" values="0;0.8;0.8;0" keyTimes="0;0.2;0.8;1" dur="3.5s" repeatCount="indefinite" />
                </circle>
                <circle cx="122" cy="185" r="1.5" fill="rgba(255,255,255,0.7)" opacity="0">
                    <animate attributeName="cy" from="185" to="{{ max(45, $barY + 5) }}" dur="2.8s" begin="0.8s" repeatCount="indefinite" />
                    <animate attributeName="opacity" values="0;0.8;0.8;0" keyTimes="0;0.2;0.8;1" dur="2.8s" begin="0.8s" repeatCount="indefinite" />
                </circle>
                <circle cx="116" cy="175" r="2" fill="rgba(255,255,255,0.7)" opacity="0">
                    <animate attributeName="cy" from="175" to="{{ max(45, $barY + 5) }}" dur="4.2s" begin="1.5s" repeatCount="indefinite" />
                    <animate attributeName="opacity" values="0;0.8;0.8;0" keyTimes="0;0.2;0.8;1" dur="4.2s" begin="1.5s" repeatCount="indefinite" />
                </circle>
                @endif

                {{-- Level percentage label --}}
                <text data-realtime-field="level-text" x="60" y="130" text-anchor="middle"
                      font-size="18" font-weight="bold" fill="{{ $levelOutOfRange ? '#ef4444' : '#1f2937' }}">
                    {{ $level }}%
                </text>

                {{-- Per-card gradient defs --}}
                <defs>
                    <linearGradient id="{{ $uid }}_waterGrad" x1="0" y1="0" x2="0" y2="1">
                        @if($levelOutOfRange)
                            <stop offset="0%" stop-color="#fca5a5"/> {{-- light red --}}
                            <stop offset="100%" stop-color="#dc2626"/> {{-- deep red --}}
                        @else
                            <stop offset="0%" stop-color="#38bdf8"/> {{-- light cyan/blue --}}
                            <stop offset="100%" stop-color="#0284c7"/> {{-- deep blue --}}
                        @endif
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p0">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p1">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p2">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p3">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p4">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p5">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p6">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p7">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p8">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p9">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                    <linearGradient id="{{ $uid }}_p10">
                        <stop stop-color="#939393"/><stop offset="0.5" stop-color="#F9F9F9"/>
                        <stop offset="1" stop-color="#939393"/>
                    </linearGradient>
                </defs>
            </svg>
        </div>

        {{-- Parameter Readings --}}
        <div class="flex-1 grid grid-rows-3 gap-2.5 min-w-0">

            {{-- pH Level --}}
            <div class="bg-gray-50 rounded-xl px-3 py-2.5 border border-gray-100">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">pH Level</span>
                    <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                        </svg>
                    </div>
                </div>
                <p data-realtime-field="ph" class="text-xl font-bold {{ $phOutOfRange ? 'text-red-600' : 'text-gray-800' }} leading-none">{{ $device['ph'] ?? '—' }}</p>
                <p class="text-xs text-gray-400 mt-1">Range {{ $device->setting->ph_min ?? '6.5' }} – {{ $device->setting->ph_max ?? '8.5' }}</p>
            </div>

            {{-- Turbidity --}}
            <div class="bg-gray-50 rounded-xl px-3 py-2.5 border border-gray-100">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Turbidity</span>
                    <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/>
                        </svg>
                    </div>
                </div>
                <p data-realtime-field="turbidity" class="text-xl font-bold {{ $turbidityOutOfRange ? 'text-red-600' : 'text-gray-800' }} leading-none">{{ $device['turbidity'] !== null ? $device['turbidity'].' NTU' : '—' }}</p>
                <p class="text-xs text-gray-400 mt-1">Target &lt;{{ $device->setting->turbidity_max ?? '5' }} NTU</p>
            </div>

            {{-- Water Usage --}}
            <div class="bg-gray-50 rounded-xl px-3 py-2.5 border border-gray-100">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Usage</span>
                    <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor"
                             stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                </div>
                <div class="flex items-end justify-between">
                    <div>
                        <p data-realtime-field="usage-today" class="text-xl font-bold text-gray-800 leading-none">
                            {{ number_format((float) ($device['usage_today'] ?? 0), 2) }}<span class="text-xs font-medium text-gray-400 ml-1">L</span>
                        </p>
                        <p class="text-xs text-gray-400">Today</p>
                    </div>
                    <div class="text-right">
                        <p data-realtime-field="usage-month-kl" class="text-sm font-bold text-gray-700">{{ number_format((float) ($device['usage_month'] ?? 0), 2) }} KL</p>
                        <p class="text-xs text-gray-400">Month</p>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ── Status Tank & Drain valve ───────────────────────────────────────────────── --}}
    <div class="flex items-stretch justify-between pt-2 border-t border-gray-100 gap-4">
        <div class="w-1/2 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status Kualitas</span>
                <div class="w-6 h-6 rounded-full bg-sky-100 flex items-center justify-center ml-1">
                    {{-- <svg viewBox="0 0 100 100" class="w-4 h-4">
                        <defs>
                            <!-- Gradien untuk efek air yang segar -->
                            <linearGradient id="waterQualityGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#81D4FA" />
                            <stop offset="100%" stop-color="#0288D1" />
                            </linearGradient>
                            
                            <!-- Bayangan halus untuk memberikan kedalaman -->
                            <filter id="dropShadow" x="-20%" y="-20%" width="140%" height="140%">
                            <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#000000" flood-opacity="0.15" />
                            </filter>
                        </defs>
                        
                        <!-- Bentuk Utama Tetesan Air -->
                        <path d="M50 10 C 50 10, 15 45, 15 65 A 35 35 0 0 0 85 65 C 85 45, 50 10, 50 10 Z" 
                                fill="url(#waterQualityGrad)" 
                                filter="url(#dropShadow)"/>
                                
                        <!-- Ikon Tanda Centang (Quality/Lulus Uji) -->
                        <path d="M35 62 L 45 72 L 65 48" 
                                fill="none" 
                                stroke="#FFFFFF" 
                                stroke-width="7" 
                                stroke-linecap="round" 
                                stroke-linejoin="round" />
                    </svg> --}}

                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" 
                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/>
                        
                        <path d="M8.5 14l2.5 2.5l4.5-5"/>
                    </svg>

                </div>
            </div>

            @php
                $qualityStatus = in_array($device->overall_health, ['good', 'warning', 'bad'], true)
                    ? $device->overall_health
                    : 'unknown';
                [$qualityColor, $qualityLabel] = match ($qualityStatus) {
                    'good' => ['text-green-600', 'BAIK'],
                    'warning' => ['text-yellow-600', 'PERLU PERHATIAN'],
                    'bad' => ['text-red-600', 'BURUK'],
                    default => ['text-gray-500', 'BELUM ADA DATA'],
                };
            @endphp
            <div
                data-realtime-field="quality-status"
                data-realtime-status="{{ $qualityStatus }}"
                class="flex items-center gap-2 font-semibold {{ $qualityColor }}"
            >
                <svg data-realtime-quality-icon="good" viewBox="0 0 60 60" class="h-5 w-5 fill-current {{ $qualityStatus === 'good' ? '' : 'hidden' }}" aria-hidden="true">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M30 60A30 30 0 1 1 60 30 30 30 0 0 1 30 60ZM13.014 36.765a3.484 3.484 0 0 1 0-4.9l1.766-1.756a3.185 3.185 0 0 1 4.574.051l3.12 3.237a1.592 1.592 0 0 0 2.311 0l15.9-16.39a3.187 3.187 0 0 1 4.6-.027L47 18.714a3.482 3.482 0 0 1 0 4.846L25.891 45.011a3.185 3.185 0 0 1-4.552.03Z"/>
                </svg>
                <svg data-realtime-quality-icon="warning" viewBox="0 0 20 20" class="h-6 w-6 fill-current {{ $qualityStatus === 'warning' ? '' : 'hidden' }}" aria-hidden="true">
                    <path d="M10 2c4.42 0 8 3.58 8 8s-3.58 8-8 8-8-3.58-8-8 3.58-8 8-8Zm1.13 9.38.35-6.46H8.52l.35 6.46h2.26Zm-.09 3.36c.24-.23.37-.55.37-.96 0-.42-.12-.74-.36-.97s-.59-.35-1.06-.35-.82.12-1.07.35-.37.55-.37.97c0 .41.13.73.38.96.26.23.61.34 1.06.34s.8-.11 1.05-.34Z"/>
                </svg>
                <svg data-realtime-quality-icon="bad" viewBox="0 0 416.979 416.979" class="h-5 w-5 fill-current {{ $qualityStatus === 'bad' ? '' : 'hidden' }}" aria-hidden="true">
                    <path d="M355.914 61.065c-81.42-81.42-213.428-81.42-294.849 0s-81.421 213.427 0 294.849c81.42 81.42 213.428 81.42 294.849 0 81.42-81.422 81.42-213.429 0-294.849Zm-43.389 197.698a11.4 11.4 0 0 1 0 16.129l-37.632 37.632a11.4 11.4 0 0 1-16.13 0l-50.273-50.275-50.275 50.275a11.4 11.4 0 0 1-16.128 0l-37.632-37.632a11.4 11.4 0 0 1 0-16.127l50.275-50.276-50.275-50.275a11.4 11.4 0 0 1 0-16.128l37.633-37.632a11.4 11.4 0 0 1 16.127 0l50.275 50.275 50.274-50.275a11.4 11.4 0 0 1 16.129 0l37.632 37.632a11.4 11.4 0 0 1 0 16.128l-50.275 50.275Z"/>
                </svg>
                <span data-realtime-quality-icon="unknown" class="h-2.5 w-2.5 rounded-full bg-current opacity-40 {{ $qualityStatus === 'unknown' ? '' : 'hidden' }}" aria-hidden="true"></span>
                <span data-realtime-quality-label class="font-bold">{{ $qualityLabel }}</span>
            </div>

        </div>

        {{-- Valve State --}}
        <div class="w-1/2 rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
            <div class="flex items-center justify-between mb-1">
                <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Drain Valve Status</span>
                <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center ml-1">
                    <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 1024 1024"><path d="M937.94 287.1a44.84 44.84 0 0 0-44.84 0L512 507.12 130.9 287.1a44.84 44.84 0 0 0-44.84 0c-13.88 8-22.42 22.81-22.42 38.83v465.96c0 16.03 8.54 30.82 22.42 38.84 6.94 4.01 14.67 6 22.42 6s15.48-1.99 22.42-6L512 610.68l381.1 220.03c6.94 4.01 14.67 6 22.42 6s15.48-1.99 22.42-6a44.826 44.826 0 0 0 22.42-38.84V325.93c-0.01-16.02-8.54-30.83-22.42-38.83zM153.32 714.23V403.58L422.33 558.9 153.32 714.23z m717.36 0L601.67 558.9l269.01-155.33v310.66z" fill="#333333"/><path d="M362.76 276.95h104.4v90c0 24.76 20.08 44.84 44.84 44.84 24.76 0 44.84-20.08 44.84-44.84v-90h104.43c24.76 0 44.84-20.08 44.84-44.84 0-24.76-20.08-44.84-44.84-44.84h-298.5c-24.76 0-44.84 20.08-44.84 44.84-0.01 24.77 20.07 44.84 44.83 44.84z" fill="#333333"/></svg>
                </div>
            </div>

            <label for="valve-{{ $device->id }}" class="inline-flex items-center gap-3 cursor-pointer valve-control">
                <input
                    id="valve-{{ $device->id }}"
                    type="checkbox"
                    class="peer sr-only valve-toggle"
                    data-device="{{ $device->id }}"
                    data-device-name="{{ $device->device_tag }}"
                    data-original="{{ $valve ? 1 : 0 }}"
                    {{ $valve ? 'checked' : '' }}
                />

                <div
                    class="relative h-6 w-11 rounded-full border border-neutral-300 bg-green-400
                    after:h-5 after:w-5 after:absolute after:bottom-0 after:left-[0.0625rem] after:top-0 after:my-auto
                    after:rounded-full after:bg-white after:transition-all after:content-['']
                    peer-checked:after:translate-x-5 peer-checked:bg-red-500"
                    aria-hidden="true">
                </div>
                <span class="text-lg font-bold valve-state-label">{{ $valve ? 'Open' : 'Closed' }}</span>
            </label>
        </div>
    </div>

    {{-- ── Footer ───────────────────────────────────────────────── --}}
    @endif
    <div class="flex items-center justify-between pt-2 border-t border-gray-100">
        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-500">
            <div data-realtime-field="alarm-status" class="flex items-center gap-1.5">
                @if(($device['alarm_count'] ?? 0) > 0)
                    <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                    <span class="font-medium text-red-600">{{ $device['alarm_count'] }} alarm active</span>
                @else
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>No active alarms</span>
                @endif
            </div>

            <span class="hidden h-3 w-px bg-gray-200 sm:block" aria-hidden="true"></span>

            @if($device->latestData || ($device->sources_count ?? 0) === 0)
            <div class="flex min-w-0 items-center gap-1.5 valve-command-status" aria-live="polite">
                <span class="hidden h-3 w-3 shrink-0 animate-spin rounded-full border-2 border-gray-300 border-t-orange-500 valve-spinner"></span>
                <span class="truncate valve-status-text">Drain Valve ready</span>
            </div>
            @endif
        </div>

        <a href="{{ route('devices.show', $device) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700
                  text-white text-xs font-semibold rounded-lg transition-colors">
            Detail
            <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </a>
    </div>

</div>
