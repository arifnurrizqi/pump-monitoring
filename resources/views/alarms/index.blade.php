<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Alarm</h1>
        <p class="text-sm text-gray-500 mt-1">Riwayat peringatan parameter pompa dan sensor</p>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            <svg class="w-4 h-4 shrink-0 fill-emerald-500" viewBox="0 0 20 20">
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0
                         0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06
                         1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- Filter card --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm mb-4">
        <form method="GET" class="flex flex-wrap items-end gap-3 px-5 py-4">

            {{-- Status --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</label>
                <select name="status"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900
                               focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors min-w-[140px]">
                    <option value="">All Status</option>
                    <option value="active"       {{ request('status') === 'active'       ? 'selected' : '' }}>Active</option>
                    <option value="acknowledged" {{ request('status') === 'acknowledged' ? 'selected' : '' }}>Acknowledged</option>
                    <option value="resolved"     {{ request('status') === 'resolved'     ? 'selected' : '' }}>Resolved</option>
                </select>
            </div>

            {{-- Device --}}
            @if(isset($devices))
            <div class="flex flex-col gap-1">
                <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Gateway</label>
                <select name="device_id"
                        class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900
                               focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors min-w-[210px]">
                    <option value="">Semua gateway</option>
                    @foreach($devices as $d)
                        <option value="{{ $d->id }}" {{ request('device_id') == $d->id ? 'selected' : '' }}>
                            {{ $d->device_tag }}
                        </option>
                    @endforeach
                </select>
            </div>
            @endif

            <x-primary-button type="submit">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16z"/>
                </svg>
                Filter
            </x-primary-button>

        </form>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Alarm Logs</h3>
            @if(isset($alarms))
                <span class="text-xs text-gray-400">{{ $alarms->total() ?? $alarms->count() }} records</span>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                        <th class="px-5 py-3 whitespace-nowrap">Device</th>
                        <th class="px-5 py-3 whitespace-nowrap">Alarm Code</th>
                        {{-- <th class="px-5 py-3 whitespace-nowrap">Message</th> --}}
                        <th class="px-5 py-3 whitespace-nowrap">Value</th>
                        <th class="px-5 py-3 whitespace-nowrap">Threshold</th>
                        <th class="px-5 py-3 whitespace-nowrap">Status</th>
                        <th class="px-5 py-3 whitespace-nowrap">Started at</th>
                        <th class="px-5 py-3 whitespace-nowrap">Resolved at</th>
                        <th class="px-5 py-3 whitespace-nowrap">Ack at</th>
                        <th class="px-5 py-3 whitespace-nowrap">Ack by</th>
                        <th class="px-5 py-3 whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($alarms as $alarm)
                        <tr class="hover:bg-gray-50 transition-colors">

                            <td class="px-5 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                {{ $alarm->device->device_tag ?? '—' }}
                            </td>

                            <td class="px-5 py-3 font-mono text-xs font-semibold text-gray-700 whitespace-nowrap uppercase">
                                {{ $alarm->alarm_code }}
                            </td>

                            {{-- <td class="px-5 py-3 text-gray-600 max-w-xs truncate">
                                {{ $alarm->alarm_message }}
                            </td> --}}

                            <td class="px-5 py-3 font-mono text-gray-700 whitespace-nowrap">
                                {{ $alarm->current_value ?? '—' }}
                            </td>

                            <td class="px-5 py-3 font-mono text-gray-500 whitespace-nowrap">
                                {{ $alarm->threshold_value ?? '—' }}
                            </td>

                            <td class="px-5 py-3 whitespace-nowrap">
                                @php
                                    $badge = match($alarm->status) {
                                        'active'       => 'bg-red-100 text-red-700',
                                        'acknowledged' => 'bg-amber-100 text-amber-700',
                                        'resolved'     => 'bg-emerald-100 text-emerald-700',
                                        default        => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $badge }}">
                                    {{ ucfirst($alarm->status) }}
                                </span>
                            </td>

                            <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                {{ $alarm->started_at?->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                {{ $alarm->resolved_at?->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                {{ $alarm->acknowledged_at?->format('d M Y, H:i') ?? '—' }}
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                {{ $alarm->acknowledgedBy?->name ?? '-' }}
                            </td>

                            <td class="px-5 py-3 whitespace-nowrap">
                                @if($alarm->status === 'active')
                                    <form method="POST" action="{{ route('alarms.ack', $alarm) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 bg-blue-600 hover:bg-blue-700
                                                       text-white text-xs font-semibold rounded-lg transition-colors">
                                            Acknowledge
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-300">—</span>
                                @endif
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-12 text-center">
                                <div class="flex flex-col items-center gap-2 text-gray-400">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                              d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0
                                                 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312
                                                 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255
                                                 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                                    </svg>
                                    <span class="text-sm font-medium">No alarms found</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(isset($alarms) && $alarms->hasPages())
            <div class="px-5 py-4 border-t border-gray-100">
                {{ $alarms->withQueryString()->links() }}
            </div>
        @endif
    </div>

</x-app-layout>
