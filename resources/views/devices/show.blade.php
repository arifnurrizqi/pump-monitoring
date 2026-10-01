<x-app-layout>
    @php($lastSeen = $device->last_seen_at)
    <div class="space-y-6" data-realtime-device-id="{{ $device->id }}" data-status="{{ $device->connection_status }}" data-last-seen-at="{{ $lastSeen?->toIso8601String() }}" data-send-interval="{{ $device->setting?->send_interval_sec ?? 10 }}" data-active-alarm-count="{{ $device->activeAlarms->count() }}">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <a href="{{ $backUrl }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">&larr; Semua gateway</a>
                <p class="mt-4 text-xs font-semibold uppercase tracking-widest text-slate-400">{{ $device->site?->name ?? 'Lokasi belum diatur' }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $device->device_tag }}</h1>
                <p class="mt-1 font-mono text-xs text-slate-500">{{ $device->device_sn }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(in_array($myRole, ['owner', 'operator']))
                    <a href="{{ route('devices.monitoring.index', $device) }}" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">Atur sumber &amp; alarm</a>
                    <a href="{{ route('devices.edit', $device) }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Edit gateway</a>
                @endif
                @if($myRole === 'owner')
                    <form action="{{ route('devices.destroy', $device) }}" method="POST" onsubmit="return confirm('Hapus gateway ini dari akun Anda?')">@csrf @method('DELETE')<button class="rounded-xl border border-red-200 bg-white px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50">Hapus</button></form>
                @endif
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            @php($status = $device->connection_status)
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Koneksi gateway</p>
                <span data-realtime-field="connection-status" data-status="{{ $status }}" class="mt-2 inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $status === 'online' ? 'bg-emerald-50 text-emerald-700' : ($status === 'pending' ? 'bg-amber-50 text-amber-700' : 'bg-slate-100 text-slate-600') }}"><span data-realtime-status-label>{{ $status === 'pending' ? 'Terlambat' : ucfirst($status) }}</span></span>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Paket terakhir diterima</p>
                <p class="mt-2 text-sm font-semibold text-slate-900" data-realtime-field="last-seen">{{ $lastSeen?->diffForHumans() ?? 'Belum ada data' }}</p>
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">Alarm aktif</p>
                <p class="mt-2 text-sm font-semibold {{ $device->activeAlarms->isNotEmpty() ? 'text-red-600' : 'text-emerald-700' }}" data-realtime-field="alarm-status">{{ $device->activeAlarms->isNotEmpty() ? $device->activeAlarms->count().' alarm aktif' : 'Tidak ada alarm aktif' }}</p>
            </div>
        </div>

        @if($device->sources->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                <h2 class="text-base font-semibold text-slate-800">Belum ada sumber data</h2>
                <p class="mt-1 text-sm text-slate-500">Daftarkan power meter, sensor tekanan, flow, atau temperatur yang dibaca gateway.</p>
                @if(in_array($myRole, ['owner', 'operator']))<a href="{{ route('devices.monitoring.index', $device) }}" class="mt-5 inline-flex rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Atur sumber data</a>@endif
            </div>
        @else
            <div class="space-y-4">
                <div><h2 class="text-base font-semibold text-slate-900">Pembacaan langsung</h2><p class="text-xs text-slate-500">Angka berubah saat gateway mengirim pesan MQTT.</p></div>
                @foreach($device->sources as $source)
                    <section class="rounded-2xl border border-slate-200 bg-white shadow-sm" data-source-id="{{ $source->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                            <div><h3 class="font-semibold text-slate-900">{{ $source->name }}</h3><p class="mt-0.5 text-xs text-slate-500">{{ $source->asset?->name ?? $source->source_key }} · {{ str_replace('_', ' ', $source->type) }}</p></div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $source->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $source->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3 xl:grid-cols-4">
                            @forelse($source->parameters as $parameter)
                                @php($reading = $sourceReadings[$source->id][$parameter->key] ?? null)
                                <div class="rounded-xl border p-3 {{ ($reading['quality'] ?? 'good') === 'error' ? 'border-red-200 bg-red-50' : ((($reading['quality'] ?? 'good') === 'uncertain') ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-slate-50') }}" data-source-parameter="{{ $parameter->key }}">
                                    <p class="text-xs font-medium text-slate-500">{{ $parameter->label }}</p>
                                    <p class="mt-2 font-semibold tabular-nums text-slate-900"><span class="text-xl" data-parameter-value>{{ $reading && $reading['value'] !== null ? number_format($reading['value'], 2, ',', '.') : '—' }}</span> <span class="text-xs font-normal text-slate-500">{{ $parameter->unit }}</span></p>
                                    <div class="mt-2 flex flex-wrap gap-x-2 text-[11px] text-slate-400"><span data-parameter-quality>{{ match($reading['quality'] ?? null) { 'good' => 'Baik', 'uncertain' => 'Ragu', 'error' => 'Gagal', default => 'Belum ada data' } }}</span><span data-parameter-time>{{ !empty($reading['recorded_at']) ? \Illuminate\Support\Carbon::parse($reading['recorded_at'])->format('H:i:s') : '' }}</span></div>
                                    @if($parameter->alarm_enabled && ($parameter->alarm_min !== null || $parameter->alarm_max !== null))<p class="mt-1 text-[11px] text-slate-500">Batas: {{ $parameter->alarm_min ?? '—' }} – {{ $parameter->alarm_max ?? '—' }} {{ $parameter->unit }}</p>@endif
                                </div>
                            @empty
                                <p class="col-span-full text-sm text-slate-500">Belum ada parameter pada sumber ini.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            </div>

            <section class="space-y-4" data-chart-dashboard data-chart-url="{{ route('devices.charts', $device) }}">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div><h2 class="text-base font-semibold text-slate-900">Tren parameter</h2><p class="mt-0.5 text-xs text-slate-500">Rata-rata histori per interval; pembacaan MQTT baru tampil langsung.</p></div>
                    <div class="flex flex-wrap gap-1 rounded-xl border border-slate-200 bg-white p-1" role="group" aria-label="Rentang waktu grafik">
                        @foreach(['1h' => '1 jam', '12h' => '12 jam', '1d' => '1 hari', '7d' => '7 hari', '1mo' => '1 bulan'] as $range => $label)
                            <button type="button" data-chart-range="{{ $range }}" aria-pressed="{{ $range === '1h' ? 'true' : 'false' }}" class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ $range === '1h' ? 'bg-blue-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                @if(count($chartGroups))
                    <div class="grid gap-4 xl:grid-cols-2">
                        @foreach($chartGroups as $group)
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" data-chart-group="{{ $group['key'] }}">
                                <h3 class="font-semibold text-slate-900">{{ $group['title'] }}</h3>
                                <p class="mt-0.5 text-xs text-slate-500">{{ count($group['series']) }} parameter</p>
                                <div class="mt-4 h-64"><canvas aria-label="Grafik {{ $group['title'] }}" role="img"></canvas></div>
                                <p data-chart-empty class="hidden py-4 text-sm text-slate-500">Belum ada data pada rentang ini.</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Belum ada parameter yang dipilih untuk grafik. Atur grup grafik di pengaturan monitoring.</div>
                @endif
                <p data-chart-error class="hidden text-sm text-red-600">Histori grafik gagal dimuat. Coba pilih rentang waktu lagi.</p>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Histori interval</h2><p class="mt-0.5 text-xs text-slate-500">Maksimal 60 interval terakhir per sumber.</p></div>
                <div class="divide-y divide-slate-100">
                    @foreach($device->sources as $source)
                        <details class="group px-5 py-4"><summary class="flex cursor-pointer items-center justify-between font-medium text-slate-800">{{ $source->name }} <span class="text-xs text-slate-400">{{ $sourceHistory[$source->id]->count() }} interval &darr;</span></summary>
                            <div class="mt-3 overflow-x-auto"><table class="min-w-full text-left text-xs"><thead><tr class="bg-slate-50 text-slate-500"><th class="whitespace-nowrap p-2">Waktu</th><th class="p-2">Sampel</th>@foreach($source->parameters as $parameter)<th class="whitespace-nowrap p-2">{{ $parameter->label }}</th>@endforeach</tr></thead><tbody>
                                @forelse($sourceHistory[$source->id]->reverse() as $interval)<tr class="border-t border-slate-100"><td class="whitespace-nowrap p-2">{{ $interval->bucket_start->format('d M H:i') }}</td><td class="p-2">{{ $interval->sample_count }}</td>@foreach($source->parameters as $parameter)@php($metric = $interval->metrics[$parameter->key] ?? null)<td class="p-2">{{ $metric && ($metric['count'] ?? 0) > 0 ? number_format($metric['sum'] / $metric['count'], 2, ',', '.') : '—' }} {{ $parameter->unit }}</td>@endforeach</tr>
                                @empty<tr><td colspan="{{ $source->parameters->count() + 2 }}" class="p-5 text-center text-slate-500">Belum ada histori.</td></tr>@endforelse
                            </tbody></table></div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Alarm gateway</h2><a href="{{ route('alarms.index') }}" class="text-xs font-medium text-blue-600">Semua alarm &rarr;</a></div>
            @forelse($device->activeAlarms as $alarm)<div class="border-b border-slate-100 px-5 py-3 last:border-0"><p class="text-sm font-semibold text-red-700">{{ $alarm->alarm_message }}</p><p class="mt-1 text-xs text-slate-400">{{ $alarm->started_at?->format('d M Y H:i') }}</p></div>
            @empty<p class="px-5 py-6 text-sm text-slate-500">Tidak ada alarm aktif.</p>@endforelse
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="font-semibold text-slate-900">Akses pengguna</h2></div>
            <div class="space-y-4 p-5">
                @if($myRole === 'owner')
                    <form action="{{ route('devices.share', $device) }}" method="POST" class="flex flex-wrap gap-2">@csrf
                        <input type="email" name="email" required placeholder="Email pengguna" class="min-w-48 flex-1 rounded-lg border-slate-200 text-sm">
                        <select name="role" class="rounded-lg border-slate-200 text-sm"><option value="viewer">Viewer</option><option value="operator">Operator</option></select>
                        <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Bagikan akses</button>
                    </form>
                @endif
                @if($errors->any())<div class="text-sm text-red-600">{{ $errors->first() }}</div>@endif
                <div class="divide-y divide-slate-100">
                    @foreach($device->users as $user)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-3">
                            <div><p class="text-sm font-medium text-slate-800">{{ $user->name }}</p><p class="text-xs text-slate-500">{{ $user->email }}</p></div>
                            <div class="flex items-center gap-2">
                                @if($myRole === 'owner' && $user->pivot->role !== 'owner')
                                    <form action="{{ route('devices.access.update', [$device, $user]) }}" method="POST" class="flex items-center gap-1">@csrf @method('PATCH')<select name="role" class="rounded-lg border-slate-200 text-xs"><option value="viewer" @selected($user->pivot->role === 'viewer')>Viewer</option><option value="operator" @selected($user->pivot->role === 'operator')>Operator</option></select><button class="text-xs font-medium text-blue-600">Simpan</button></form>
                                    <form action="{{ route('devices.access.remove', [$device, $user]) }}" method="POST">@csrf @method('DELETE')<button class="text-xs font-medium text-red-600">Hapus</button></form>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">{{ ucfirst($user->pivot->role) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <script>window.__PUMP_CHART_GROUPS__ = @json(array_values($chartGroups));</script>
    @push('scripts') @vite('resources/js/pump-detail.js') @endpush
</x-app-layout>
