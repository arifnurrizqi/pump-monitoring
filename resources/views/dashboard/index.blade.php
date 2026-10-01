<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Dashboard</h1>
        <p class="text-sm text-gray-500 mt-1">Water Analyzer Monitoring</p>
    </div>

    {{-- ══════════════════════════════════════════════════════════
         1. SUMMARY STAT CARDS
    ══════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-2 xl:grid-cols-6 gap-4 mb-6">

        {{-- Total Devices --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 fill-blue-600" viewBox="0 0 48 48">
                    <rect width="48" height="48" fill="none"/>
                    <path d="M2 32v10a2 2 0 0 0 2 2h40a2 2 0 0 0 2-2V32zm30 6a2 2 0 1 1 2-2 2 2 0 0 1-2
                             2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2zM44 4H4a2 2 0 0 0-2 2v10h44V6a2 2 0 0 0-2-2zm-12
                             10a2 2 0 1 1 2-2 2 2 0 0 1-2 2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2zM2 20v8h44v-8zm30
                             6a2 2 0 1 1 2-2 2 2 0 0 1-2 2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 font-medium">Total Devices</p>
                <p class="text-2xl font-bold text-gray-900 leading-tight">{{ $totalDevices }}</p>
                <p class="text-xs text-gray-500 leading-tight">Unit</p>

            </div>
        </div>

        {{-- Online --}}
        <div class="bg-white rounded-2xl border border-emerald-200 shadow-sm p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 30 30" class="w-6 h-6 text-green-500">
                <g transform="translate(0,-289.0625)">
                    <path style="fill:currentColor;fill-opacity:1;stroke:none;" 
                        d="m 3.0000004,298.27303 c 0,0 4.9999981,-4.21053 11.9999996,-4.21053 7.000001,0 12,4.21053 12,4.21053 L 15.000001,314.0625 Z">
                    </path>
                </g>
                </svg>

            </div>
            <div class="min-w-0">
                <p class="text-xs text-emerald-600 font-medium">Online</p>
                <p data-realtime-summary="online" class="text-2xl font-bold text-gray-900 leading-tight">{{ $onlineCount }}</p>
                <p class="text-xs text-emerald-500 leading-tight">↑ Stable</p>
            </div>
        </div>

        {{-- Pending --}}
        <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 30 30" class="w-6 h-6 text-amber-500" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor" 
                        d="M 15 5 C 7.9999985 5 3 9.2109375 3 9.2109375 L 15 25 L 18 21.052734 L 18 9 L 25 9 L 25 11.841797 L 27 9.2109375 C 27 9.2109375 22.000001 5 15 5 z" />
                
                <path fill="currentColor" 
                        transform="translate(-0.136191, -288.94653)" 
                        d="m 23.495577,309.8885 h -2.905415 l -0.453971,-9.94197 h 3.813357 z m -1.452707,2.06556 q 0.862545,0 1.452707,0.61286 0.612861,0.59017 0.612861,1.43001 0,0.86255 -0.612861,1.47541 -0.590162,0.59016 -1.452707,0.59016 -0.839847,0 -1.452708,-0.59016 Q 20,314.85948 20,313.99693 q 0,-0.83984 0.590162,-1.43001 0.612861,-0.61286 1.452708,-0.61286 z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-amber-600 font-medium">Pending</p>
                <p data-realtime-summary="pending" class="text-2xl font-bold text-gray-900 leading-tight">{{ $pendingCount }}</p>
                <p class="text-xs text-amber-500 leading-tight">⚠ Checking</p>
            </div>
        </div>

        {{-- Offline --}}
        <div class="bg-white rounded-2xl border border-red-200 shadow-sm p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 30 30" class="w-6 h-6 text-red-500" xmlns="http://www.w3.org/2000/svg">
                <path fill="currentColor" 
                        d="M 25.607422 4.3925781 L 4.3925781 25.607422 L 5.8066406 27.021484 L 11.902344 20.925781 L 15 25 L 27 9.2109375 C 27 9.2109375 26.267944 8.5994968 24.984375 7.84375 L 27.021484 5.8066406 L 25.607422 4.3925781 z M 15 5 C 7.9999985 5 3 9.2109375 3 9.2109375 L 9.4609375 17.710938 L 21.113281 6.0585938 C 19.357071 5.4527322 17.288639 5 15 5 z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-red-500 font-medium">Offline</p>
                <p data-realtime-summary="offline" class="text-2xl font-bold text-gray-900 leading-tight">{{ $offlineCount }}</p>
                <p class="text-xs text-red-400 leading-tight">↓ Needs attention</p>
            </div>
        </div>
        
        {{-- Water Usage All Analyzer --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" class="w-6 h-6 fill-blue-600"><path id="Vector" d="M16.0001 13.3848C16.0001 14.6088 15.526 15.7828 14.6821 16.6483C14.203 17.1397 13.6269 17.5091 13 17.7364M19 13.6923C19 7.11538 12 2 12 2C12 2 5 7.11538 5 13.6923C5 15.6304 5.7375 17.4893 7.05025 18.8598C8.36301 20.2302 10.1436 20.9994 12.0001 20.9994C13.8566 20.9994 15.637 20.2298 16.9497 18.8594C18.2625 17.4889 19 15.6304 19 13.6923Z" stroke="#fffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g> </g></svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 font-medium">Total Usage (Today)</p>
                <p data-realtime-summary="usage-today" class="text-2xl font-bold text-gray-900 leading-tight">{{ number_format($totalUsageTodayLiter / 1000, 2) }} KL</p>
                @if($usageTrendPercent !== null)
                    <p class="text-xs {{ $usageTrendPercent > 0 ? 'text-red-400' : 'text-emerald-500' }} leading-tight">
                        {{ $usageTrendPercent > 0 ? '↑' : '↓' }} {{ number_format(abs($usageTrendPercent), 1) }}% dari kemarin
                    </p>
                @else
                    <p class="text-xs text-gray-400 leading-tight">Belum ada pembanding kemarin</p>
                @endif
            </div>
        </div>

        {{-- Monthly Water Usage --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-4 flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-cyan-50 flex items-center justify-center shrink-0">
                <svg viewBox="0 0 24 24" class="w-6 h-6 fill-none stroke-cyan-600" stroke-width="1.8">
                    <path d="M7 3v3M17 3v3M4 9h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
                    <path d="M12 12c-1.5 1.7-2.2 2.8-2.2 4a2.2 2.2 0 0 0 4.4 0c0-1.2-.7-2.3-2.2-4Z" />
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 font-medium">Total Usage (This Month)</p>
                <p data-realtime-summary="usage-month" class="text-2xl font-bold text-gray-900 leading-tight">{{ number_format($totalUsageThisMonthLiter / 1000, 2) }} KL</p>
                <p class="text-xs text-gray-400 leading-tight">Sejak awal bulan</p>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════════════════
         2. DEVICE MONITORING CARDS  (pH Level SVG + readings)
    ══════════════════════════════════════════════════════════ --}}
    @if($devices->count())
        <div class="mb-6">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                Device Monitoring
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4">
                @foreach($devices as $device)
                {{-- <pre>
                    {{ print_r($device->toArray(), true) }}
                </pre> --}}
                    <x-device-card :device="$device" />
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════
         3. DATA TABLE + PROBLEM DEVICES
    ══════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">

        {{-- Device Table (takes 2/3 on xl) --}}
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Table header --}}
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-800">All Devices</h3>
                <a href="{{ route('devices.index') }}"
                   class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
                    View all →
                </a>
            </div>

            {{-- Scrollable table --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 whitespace-nowrap">Device</th>
                            <th class="px-5 py-3 whitespace-nowrap">Location</th>
                            <th class="px-5 py-3 whitespace-nowrap">Status</th>
                            <th class="px-5 py-3 whitespace-nowrap">pH</th>
                            <th class="px-5 py-3 whitespace-nowrap">Turbidity</th>
                            <th class="px-5 py-3 whitespace-nowrap">Level</th>
                            <th class="px-5 py-3 whitespace-nowrap">Last Seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($devices as $device)
                            <tr data-realtime-device-row="{{ $device->id }}" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-3 font-semibold text-gray-900 capitalize whitespace-nowrap">
                                    {{ $device->device_tag }}
                                </td>
                                <td class="px-5 py-3 text-gray-500 uppercase whitespace-nowrap">
                                    {{ $device->location ?? '—' }}
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @php
                                        $s = $device['status'] ?? 'offline';
                                        $badge = match($s) {
                                            'online'  => 'bg-emerald-100 text-emerald-700',
                                            'alarm'   => 'bg-red-100 text-red-700',
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            default   => 'bg-gray-100 text-gray-600',
                                        };
                                    @endphp
                                    <span data-realtime-field="connection-status" data-status="{{ $s }}" class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $badge }}">
                                        <span data-realtime-status-label>{{ ucfirst($s) }}</span>
                                    </span>
                                </td>
                                @php
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

                                    $lvl = (int)($device['level'] ?? 0);
                                    $levelMin = $device->setting->level_min_percent ?? null;
                                    $levelMax = $device->setting->level_max_percent ?? null;
                                    $levelOutOfRange = false;
                                    if ($device['level'] !== null) {
                                        if ($levelMin !== null && $lvl < $levelMin) $levelOutOfRange = true;
                                        if ($levelMax !== null && $lvl > $levelMax) $levelOutOfRange = true;
                                    }
                                @endphp
                                <td data-realtime-field="ph" class="px-5 py-3 font-mono {{ $phOutOfRange ? 'text-red-600 font-bold' : 'text-gray-700' }} whitespace-nowrap">
                                    {{ $device['ph'] ?? '—' }}
                                </td>
                                <td data-realtime-field="turbidity" class="px-5 py-3 font-mono {{ $turbidityOutOfRange ? 'text-red-600 font-bold' : 'text-gray-700' }} whitespace-nowrap">
                                    {{ $device['turbidity'] ?? '—' }} NTU
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div data-realtime-field="level-bar" class="h-full {{ $levelOutOfRange ? 'bg-red-500' : 'bg-blue-500' }} rounded-full"
                                                 style="width: {{ $lvl }}%"></div>
                                        </div>
                                        <span data-realtime-field="level-text" class="text-xs {{ $levelOutOfRange ? 'text-red-600 font-bold' : 'text-gray-500' }} tabular-nums">{{ $lvl }}%</span>
                                    </div>
                                </td>
                                <td data-realtime-field="last-seen" class="px-5 py-3 text-gray-400 whitespace-nowrap text-xs">
                                    {{ optional($device->latestData?->last_seen_at)->diffForHumans() ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-gray-400 text-sm">
                                    No devices found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Problem Devices (1/3) --}}
        <div class="flex flex-col gap-4">

            {{-- Latest Alarm summary --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">Latest Alarm</h3>
                    <a href="{{ route('alarms.index') }}"
                       class="text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
                        View all →
                    </a>
                </div>
                <div class="p-5">
                    @if($latestAlarms->count())
                        <ul class="space-y-3">
                            @foreach($latestAlarms as $alarm)
                                <li class="flex flex-col gap-1 pb-3 border-b border-gray-50 last:border-0 last:pb-0">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-2 min-w-0">
                                            @php
                                                $dotColor = match($alarm->status) {
                                                    'active'       => 'bg-red-500',
                                                    'acknowledged' => 'bg-amber-500',
                                                    'resolved'     => 'bg-emerald-500',
                                                    default        => 'bg-gray-400',
                                                };
                                                $statusBadge = match($alarm->status) {
                                                    'active'       => 'bg-red-50 text-red-700 border-red-100',
                                                    'acknowledged' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                    'resolved'     => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                                    default        => 'bg-gray-50 text-gray-600 border-gray-100',
                                                };
                                            @endphp
                                            <span class="w-2 h-2 rounded-full {{ $dotColor }} shrink-0"></span>
                                            <span class="text-sm font-semibold text-gray-800 truncate">
                                                {{ $alarm->device->device_tag ?? 'Unknown Device' }}
                                            </span>
                                        </div>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold shrink-0 border {{ $statusBadge }}">
                                            {{ ucfirst($alarm->status) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-gray-500 pl-4">
                                        <span class="font-mono text-gray-600 truncate mr-2" title="{{ $alarm->alarm_message }}">
                                            {{ $alarm->alarm_code }}: {{ $alarm->alarm_message }}
                                        </span>
                                        <span class="shrink-0 text-gray-400 text-[10px]">
                                            {{ $alarm->started_at?->diffForHumans() ?? '—' }}
                                        </span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="flex flex-col items-center justify-center py-6 text-center">
                            <div class="w-10 h-10 rounded-full bg-emerald-50 flex items-center justify-center mb-2">
                                <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-700">All Clear</p>
                            <p class="text-xs text-gray-400 mt-0.5">No alarms logged</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Quick links --}}
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Quick Access</h3>
                <div class="space-y-1">
                    <a href="{{ route('datalogger.index') }}"
                       class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-sm
                              text-gray-600 hover:bg-blue-50 hover:text-blue-700 transition-colors group">
                        <span class="font-medium">Data Logger</span>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 transition-colors"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <a href="{{ route('alarms.index') }}"
                       class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-sm
                              text-gray-600 hover:bg-blue-50 hover:text-blue-700 transition-colors group">
                        <span class="font-medium">Alarm History</span>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 transition-colors"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <a href="{{ route('devices.index') }}"
                       class="flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl text-sm
                              text-gray-600 hover:bg-blue-50 hover:text-blue-700 transition-colors group">
                        <span class="font-medium">Manage Devices</span>
                        <svg class="w-4 h-4 text-gray-300 group-hover:text-blue-500 transition-colors"
                             fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>

        </div>{{-- /right column --}}
    </div>

    <dialog id="valve-confirmation-modal"
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/60">
        <form method="dialog" class="overflow-hidden rounded-2xl">
            <div class="border-b border-gray-100 px-6 py-5">
                <div class="flex items-start gap-4">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900" id="valve-modal-title">Confirm valve control</h2>
                        <p class="mt-1 text-sm leading-6 text-gray-500" id="valve-modal-description"></p>
                    </div>
                </div>
            </div>
            <div class="flex flex-col-reverse gap-2 bg-gray-50 px-6 py-4 sm:flex-row sm:justify-end">
                <button type="button" data-valve-modal-cancel
                        class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-offset-2">
                    Cancel
                </button>
                <button type="button" data-valve-modal-confirm
                        class="rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2">
                    Confirm
                </button>
            </div>
        </form>
    </dialog>

</x-app-layout>
