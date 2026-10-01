<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-widest text-blue-600">Monitoring produksi</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">Ringkasan pompa</h1>
                <p class="mt-1 text-sm text-slate-500">Status gateway, pembacaan sensor, dan alarm dalam satu tampilan.</p>
            </div>
            <a href="{{ route('devices.create') }}" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">+ Tambah gateway</a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-5">
            @foreach([
                ['label' => 'Gateway', 'value' => $devices->count(), 'color' => 'text-slate-900', 'field' => null],
                ['label' => 'Online', 'value' => $onlineCount, 'color' => 'text-emerald-700', 'field' => 'online'],
                ['label' => 'Terlambat', 'value' => $pendingCount, 'color' => 'text-amber-700', 'field' => 'pending'],
                ['label' => 'Offline', 'value' => $offlineCount, 'color' => 'text-slate-600', 'field' => 'offline'],
                ['label' => 'Alarm aktif', 'value' => $activeAlarmCount, 'color' => 'text-red-700', 'field' => 'alarms'],
            ] as $stat)
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium text-slate-500">{{ $stat['label'] }}</p>
                    <p @if($stat['field']) data-realtime-summary="{{ $stat['field'] }}" @endif class="mt-2 text-3xl font-bold tabular-nums {{ $stat['color'] }}">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <div><h2 class="text-base font-semibold text-slate-900">Gateway produksi</h2><p class="text-xs text-slate-500">{{ $sourceCount }} sumber data terdaftar</p></div>
                <a href="{{ route('devices.index') }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Kelola gateway &rarr;</a>
            </div>
            @if($devices->isEmpty())
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center">
                    <p class="text-base font-semibold text-slate-800">Belum ada gateway</p>
                    <p class="mt-1 text-sm text-slate-500">Tambahkan gateway, lalu daftarkan power meter dan sensor yang terhubung.</p>
                    <a href="{{ route('devices.create') }}" class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Tambah gateway pertama</a>
                </div>
            @else
                <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach($devices as $device)<x-device-card :device="$device" />@endforeach
                </div>
            @endif
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                <div><h2 class="text-sm font-semibold text-slate-900">Alarm aktif terbaru</h2><p class="mt-0.5 text-xs text-slate-500">Perlu ditinjau oleh operator</p></div>
                <a href="{{ route('alarms.index') }}" class="text-xs font-semibold text-blue-600">Lihat semua &rarr;</a>
            </div>
            @forelse($latestAlarms as $alarm)
                <div class="flex flex-col gap-2 border-b border-slate-100 px-5 py-3 last:border-0 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0"><p class="text-sm font-semibold text-slate-800">{{ $alarm->device?->device_tag }} · {{ $alarm->parameter_name ?? $alarm->alarm_code }}</p><p class="text-xs text-red-600">{{ $alarm->alarm_message }}</p></div>
                    <span class="shrink-0 text-xs text-slate-400">{{ $alarm->started_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="px-5 py-8 text-center text-sm text-slate-500">Tidak ada alarm aktif.</p>
            @endforelse
        </section>
    </div>
</x-app-layout>
