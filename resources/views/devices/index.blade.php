<x-app-layout>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight">Devices</h1>
            <p class="text-sm text-gray-500 mt-1">Manage registered monitoring devices</p>
        </div>
        <div>
            <a href="{{ route('devices.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700
                      border border-transparent rounded-xl text-sm font-semibold text-white transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Add Device
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-700">
            <svg class="w-4 h-4 shrink-0 fill-emerald-500" viewBox="0 0 20 20">
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483
                         4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5z"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

        @if($devices->count())
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 whitespace-nowrap">Tag</th>
                            <th class="px-5 py-3 whitespace-nowrap">Serial No.</th>
                            <th class="px-5 py-3 whitespace-nowrap">Location</th>
                            <th class="px-5 py-3 whitespace-nowrap">Status</th>
                            <th class="px-5 py-3 whitespace-nowrap">Last Seen</th>
                            <th class="px-5 py-3 whitespace-nowrap">Interval</th>
                            <th class="px-5 py-3 whitespace-nowrap">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($devices as $device)
                            <tr class="hover:bg-gray-50 transition-colors">

                                <td class="px-5 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                    {{ $device->device_tag }}
                                </td>

                                <td class="px-5 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">
                                    {{ $device->device_sn }}
                                </td>

                                <td class="px-5 py-3 text-gray-600 uppercase whitespace-nowrap">
                                    {{ $device->location ?? '—' }}
                                </td>

                                <td class="px-5 py-3 whitespace-nowrap">
                                    @php
                                        $cs = $device->connection_status;
                                        $badge = match($cs) {
                                            'online'  => 'bg-emerald-100 text-emerald-700',
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            default   => 'bg-gray-100 text-gray-600',
                                        };
                                        $dot = match($cs) {
                                            'online'  => 'bg-emerald-500',
                                            'pending' => 'bg-amber-400',
                                            default   => 'bg-gray-400',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-full {{ $badge }}">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>
                                        {{ ucfirst($cs) }}
                                    </span>
                                </td>

                                <td class="px-5 py-3 text-xs text-gray-400 whitespace-nowrap">
                                    {{ $device->latestData?->last_seen_at
                                        ? $device->latestData->last_seen_at->diffForHumans()
                                        : '—' }}
                                </td>

                                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                    @if($device->setting?->send_interval_sec)
                                        {{ $device->setting->send_interval_sec }}s
                                    @else
                                        —
                                    @endif
                                </td>

                                <td class="px-5 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('devices.show', $device) }}"
                                           class="px-2.5 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 text-xs font-semibold rounded-lg transition-colors">
                                            View
                                        </a>
                                        <a href="{{ route('devices.settings.edit', $device) }}"
                                           class="px-2.5 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold rounded-lg transition-colors">
                                            Settings
                                        </a>
                                        <a href="{{ route('devices.edit', $device) }}"
                                           class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-700 text-xs font-semibold rounded-lg transition-colors">
                                            Edit
                                        </a>
                                        <form action="{{ route('devices.destroy', $device) }}" method="POST"
                                              onsubmit="return confirm('Delete device {{ $device->device_tag }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold rounded-lg transition-colors">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($devices->hasPages())
                <div class="px-5 py-4 border-t border-gray-100">
                    {{ $devices->links() }}
                </div>
            @endif

        @else
            <div class="flex flex-col items-center justify-center py-16 px-8 text-center">
                <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center mb-4">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0
                                 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0
                                 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375
                                 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587
                                 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0
                                 3h.008v.008H15v-.008zm-4.875 0h.008v.008H10.125v-.008zm-4.875 0h.008v.008H5.25v-.008z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-700 mb-1">No devices yet</p>
                <p class="text-xs text-gray-400 mb-5">Register your first monitoring device to get started.</p>
                <a href="{{ route('devices.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700
                          border border-transparent rounded-xl text-sm font-semibold text-white transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                    </svg>
                    Add First Device
                </a>
            </div>
        @endif

    </div>

</x-app-layout>
