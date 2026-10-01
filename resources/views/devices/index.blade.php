<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-blue-600">Inventaris IoT</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Gateway produksi</h1><p class="mt-1 text-sm text-slate-500">Satu gateway dapat membaca beberapa power meter dan sensor.</p></div>
            <a href="{{ route('devices.create') }}" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">+ Tambah gateway</a>
        </div>

        @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('success') }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            @if($devices->count())
                <div class="overflow-x-auto"><table class="min-w-full text-left text-sm">
                    <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Gateway</th><th class="px-5 py-3">Lokasi</th><th class="px-5 py-3">Sumber</th><th class="px-5 py-3">Koneksi</th><th class="px-5 py-3">Alarm</th><th class="px-5 py-3">Terakhir diterima</th><th class="px-5 py-3">Aksi</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($devices as $device)
                            @php($status = $device->connection_status)
                            <tr class="hover:bg-slate-50" data-realtime-device-id="{{ $device->id }}" data-status="{{ $status }}" data-last-seen-at="{{ $device->last_seen_at?->toIso8601String() }}" data-send-interval="{{ $device->setting?->send_interval_sec ?? 10 }}" data-active-alarm-count="{{ $device->activeAlarms->count() }}">
                                <td class="whitespace-nowrap px-5 py-4"><p class="font-semibold text-slate-900">{{ $device->device_tag }}</p><p class="font-mono text-xs text-slate-400">{{ $device->device_sn }}</p></td>
                                <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $device->site?->name ?? $device->location ?? '—' }}</td>
                                <td class="px-5 py-4 text-slate-600">{{ $device->sources_count }}</td>
                                <td class="px-5 py-4"><span data-realtime-field="connection-status" data-status="{{ $status }}" class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $status === 'online' ? 'bg-emerald-50 text-emerald-700' : ($status === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}"><span data-realtime-status-label>{{ $status === 'pending' ? 'Terlambat' : ucfirst($status) }}</span></span></td>
                                <td class="px-5 py-4"><span data-realtime-field="alarm-status" class="text-xs {{ $device->activeAlarms->isNotEmpty() ? 'font-semibold text-red-600' : 'text-slate-500' }}">{{ $device->activeAlarms->isNotEmpty() ? $device->activeAlarms->count().' aktif' : 'Tidak ada' }}</span></td>
                                <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500" data-realtime-field="last-seen">{{ $device->last_seen_at?->diffForHumans() ?? 'Belum ada data' }}</td>
                                <td class="whitespace-nowrap px-5 py-4"><div class="flex items-center gap-2"><a href="{{ route('devices.show', $device) }}" class="font-medium text-blue-600">Detail</a><a href="{{ route('devices.monitoring.index', $device) }}" class="font-medium text-slate-600">Konfigurasi</a></div></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table></div>
                @if($devices->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $devices->links() }}</div>@endif
            @else
                <div class="px-6 py-16 text-center"><p class="font-semibold text-slate-800">Belum ada gateway</p><p class="mt-1 text-sm text-slate-500">Daftarkan gateway pertama untuk memulai monitoring.</p><a href="{{ route('devices.create') }}" class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Tambah gateway</a></div>
            @endif
        </div>
    </div>
</x-app-layout>
