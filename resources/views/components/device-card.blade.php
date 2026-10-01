@props(['device'])

@php
    $status = $device->connection_status;
    $statusStyle = match ($status) {
        'online' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        default => 'bg-slate-100 text-slate-600 ring-slate-200',
    };
    $readings = $device->relationLoaded('sourceReadings') ? $device->getRelation('sourceReadings') : [];
    $lastSeen = $device->last_seen_at;
@endphp

<article class="device-card flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md"
         data-realtime-device-id="{{ $device->id }}"
         data-status="{{ $status }}"
         data-last-seen-at="{{ $lastSeen?->toIso8601String() }}"
         data-send-interval="{{ $device->setting?->send_interval_sec ?? 10 }}"
         data-active-alarm-count="{{ $device->activeAlarms->count() }}">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $device->site?->name ?? 'Lokasi belum diatur' }}</p>
            <h3 class="mt-1 truncate text-lg font-bold text-slate-900">{{ $device->device_tag }}</h3>
            <p class="mt-0.5 font-mono text-xs text-slate-400">{{ $device->device_sn }}</p>
        </div>
        <span data-realtime-field="connection-status" data-status="{{ $status }}" class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusStyle }}">
            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
            <span data-realtime-status-label>{{ $status === 'pending' ? 'Terlambat' : ucfirst($status) }}</span>
        </span>
    </div>

    <div class="mt-5 flex-1 space-y-3">
        @forelse($device->sources->take(2) as $source)
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-3" data-source-id="{{ $source->id }}">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ $source->name }}</p>
                    <span class="shrink-0 text-[11px] text-slate-400">{{ $source->asset?->name ?? $source->source_key }}</span>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    @foreach($source->parameters->take(4) as $parameter)
                        @php($reading = $readings[$source->id][$parameter->key] ?? null)
                        <div class="min-w-0 rounded-lg border px-2.5 py-2 {{ ($reading['quality'] ?? 'good') === 'error' ? 'border-red-200 bg-red-50' : ((($reading['quality'] ?? 'good') === 'uncertain') ? 'border-amber-200 bg-amber-50' : 'border-slate-100 bg-white') }}" data-source-parameter="{{ $parameter->key }}">
                            <p class="truncate text-[11px] text-slate-500">{{ $parameter->label }}</p>
                            <p class="mt-1 truncate font-semibold tabular-nums text-slate-900"><span data-parameter-value>{{ $reading && $reading['value'] !== null ? number_format($reading['value'], 2, ',', '.') : '—' }}</span> <span class="text-[11px] font-normal text-slate-400">{{ $parameter->unit }}</span></p>
                            <span class="sr-only" data-parameter-quality>{{ match($reading['quality'] ?? null) { 'good' => 'Baik', 'uncertain' => 'Ragu', 'error' => 'Gagal', default => 'Belum ada data' } }}</span>
                            <span class="sr-only" data-parameter-time>{{ $reading['recorded_at'] ?? '' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center">
                <p class="text-sm font-medium text-slate-600">Belum ada sumber data</p>
                <p class="mt-1 text-xs text-slate-400">Tambahkan power meter atau sensor pada gateway ini.</p>
            </div>
        @endforelse
        @if($device->sources_count > 2)
            <p class="text-xs text-slate-500">+ {{ $device->sources_count - 2 }} sumber lainnya</p>
        @endif
    </div>

    <div class="mt-5 flex items-center justify-between gap-3 border-t border-slate-100 pt-4">
        <div class="min-w-0 space-y-1">
            <p data-realtime-field="alarm-status" class="text-xs font-medium {{ $device->activeAlarms->isNotEmpty() ? 'text-red-600' : 'text-emerald-700' }}">
                {{ $device->activeAlarms->isNotEmpty() ? $device->activeAlarms->count().' alarm aktif' : 'Tidak ada alarm aktif' }}
            </p>
            <p class="text-xs text-slate-400">Terakhir diterima: <span data-realtime-field="last-seen">{{ $lastSeen?->diffForHumans() ?? 'Belum ada data' }}</span></p>
        </div>
        <a href="{{ route('devices.show', $device) }}" class="shrink-0 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700">Lihat detail</a>
    </div>
</article>
