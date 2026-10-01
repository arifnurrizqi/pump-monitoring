<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight">Data Logger</h1>
            <p class="text-sm text-gray-500 mt-1">Telemetry records — {{ ucfirst($type ?? 'raw') }} view</p>
        </div>
        <div>
            <a href="{{ route('datalogger.export', request()->query()) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700
                      border border-transparent rounded-xl text-sm font-semibold text-white transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21
                             18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Export CSV
            </a>
        </div>
    </div>

    {{-- Filter toolbar --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3 px-5 py-4">

            {{-- Type --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">View</label>
                <select name="type"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900
                               focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors min-w-[130px]">
                    <option value="raw"     {{ request('type', 'raw') === 'raw'     ? 'selected' : '' }}>Raw / Interval</option>
                    <option value="hourly"  {{ request('type') === 'hourly'  ? 'selected' : '' }}>Hourly</option>
                    <option value="daily"   {{ request('type') === 'daily'   ? 'selected' : '' }}>Daily</option>
                    <option value="monthly" {{ request('type') === 'monthly' ? 'selected' : '' }}>Monthly</option>
                    <option value="yearly"  {{ request('type') === 'yearly'  ? 'selected' : '' }}>Yearly</option>
                </select>
            </div>

            {{-- Device --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Device</label>
                <select name="device_id"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900
                               focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors min-w-[160px]">
                    <option value="">All Devices</option>
                    @foreach ($devices as $device)
                        <option value="{{ $device->id }}"
                                {{ request('device_id') == $device->id ? 'selected' : '' }}>
                            {{ $device->device_tag }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- From --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">From</label>
                <input type="date" name="from" value="{{ request('from') }}"
                       class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900
                              focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors">
            </div>

            {{-- To --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">To</label>
                <input type="date" name="to" value="{{ request('to') }}"
                       class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900
                              focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors">
            </div>

            <x-primary-button type="submit">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917
                             1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432
                             5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25
                             0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0
                             0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3
                             5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3z"/>
                </svg>
                Apply Filter
            </x-primary-button>

        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Telemetry Data</h3>
            @if($logs->total())
                <span class="text-xs text-gray-400">{{ number_format($logs->total()) }} records</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3 whitespace-nowrap">Timestamp</th>
                        <th class="px-5 py-3 whitespace-nowrap">Device</th>

                        @if ($type === 'raw')
                            <th class="px-5 py-3 whitespace-nowrap">Samples</th>
                            <th class="px-5 py-3 whitespace-nowrap">pH (avg)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Turbidity (avg NTU)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Sample Flow (avg L/min)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Usage (L)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Sensor Distance (cm)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Water Height (cm)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Level (%)</th>
                            <th class="px-5 py-3 whitespace-nowrap">Signal (dBm)</th>
                        @else
                            <th class="px-5 py-3 whitespace-nowrap">pH <span class="text-gray-400 normal-case font-normal">(min/avg/max)</span></th>
                            <th class="px-5 py-3 whitespace-nowrap">Turbidity <span class="text-gray-400 normal-case font-normal">(NTU)</span></th>
                            <th class="px-5 py-3 whitespace-nowrap">Sample Flow <span class="text-gray-400 normal-case font-normal">(L/min)</span></th>
                            <th class="px-5 py-3 whitespace-nowrap">Usage <span class="text-gray-400 normal-case font-normal">(L)</span></th>
                            <th class="px-5 py-3 whitespace-nowrap">Level <span class="text-gray-400 normal-case font-normal">(%)</span></th>
                            <th class="px-5 py-3 whitespace-nowrap">Signal <span class="text-gray-400 normal-case font-normal">(dBm)</span></th>
                        @endif
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-50">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-gray-50 transition-colors">

                            {{-- Timestamp --}}
                            <td class="px-5 py-3 text-xs font-mono text-gray-500 whitespace-nowrap">
                                @if ($type === 'raw')
                                    {{ $log->recorded_at?->format('Y-m-d H:i:s') ?? '—' }}
                                @else
                                    {{ $log->period_start?->copy()->addHour()->format('Y-m-d H:i') ?? '—' }}
                                @endif
                            </td>

                            {{-- Device --}}
                            <td class="px-5 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                {{ $log->device->device_tag ?? '—' }}
                            </td>

                            @if ($type === 'raw')

                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->sample_count ?? 1 }}</td>

                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->ph ?? '—' }}</td>
                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->turbidity ?? '—' }}</td>
                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->sample_flow ?? '—' }}</td>
                                <td class="px-5 py-3 font-mono font-semibold text-blue-700 whitespace-nowrap">{{ number_format((float) $log->usage_liter, 2) }}</td>
                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->level_distance_cm ?? '—' }}</td>
                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->level_cm ?? '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @php $lvl = (int)($log->level_percent ?? 0); @endphp
                                    <div class="flex items-center gap-2">
                                        <div class="w-12 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div class="h-full bg-blue-400 rounded-full" style="width: {{ $lvl }}%"></div>
                                        </div>
                                        <span class="font-mono text-gray-700 tabular-nums">{{ $lvl }}%</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">{{ $log->signal_strength ?? '—' }}</td>

                            @else

                                {{-- Aggregated cells --}}
                                @foreach(['ph' => 'ph', 'turbidity' => 'turbidity', 'sample_flow' => 'sample_flow'] as $key => $field)
                                <td class="px-5 py-2.5 whitespace-nowrap">
                                    <div class="text-xs space-y-0.5">
                                        <div class="text-gray-400">
                                            <span class="w-6 inline-block">Min</span>
                                            <span class="font-mono text-gray-600">{{ $log->{'min_'.$field} ?? '—' }}</span>
                                        </div>
                                        <div class="text-gray-800 font-semibold">
                                            <span class="w-6 inline-block text-gray-400 font-normal">Avg</span>
                                            <span class="font-mono">{{ $log->{'avg_'.$field} ?? '—' }}</span>
                                        </div>
                                        <div class="text-gray-400">
                                            <span class="w-6 inline-block">Max</span>
                                            <span class="font-mono text-gray-600">{{ $log->{'max_'.$field} ?? '—' }}</span>
                                        </div>
                                    </div>
                                </td>
                                @endforeach
                                <td class="px-5 py-3 font-mono font-semibold text-blue-700 whitespace-nowrap">{{ number_format((float) $log->usage_liter, 2) }}</td>
                                @foreach(['level_percent' => 'level_percent', 'signal_strength' => 'signal_strength'] as $key => $field)
                                <td class="px-5 py-2.5 whitespace-nowrap">
                                    <div class="text-xs space-y-0.5">
                                        <div class="text-gray-400"><span class="w-6 inline-block">Min</span><span class="font-mono text-gray-600">{{ $log->{'min_'.$field} ?? '—' }}</span></div>
                                        <div class="text-gray-800 font-semibold"><span class="w-6 inline-block text-gray-400 font-normal">Avg</span><span class="font-mono">{{ $log->{'avg_'.$field} ?? '—' }}</span></div>
                                        <div class="text-gray-400"><span class="w-6 inline-block">Max</span><span class="font-mono text-gray-600">{{ $log->{'max_'.$field} ?? '—' }}</span></div>
                                    </div>
                                </td>
                                @endforeach

                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5
                                                 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0
                                                 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5
                                                 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
                                    </svg>
                                    <span class="text-sm font-medium">No data found</span>
                                    <span class="text-xs">Try adjusting your filter range</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $logs->withQueryString()->links() }}
            </div>
        @endif
    </div>

</x-app-layout>
