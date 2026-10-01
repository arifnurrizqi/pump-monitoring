<x-app-layout>
    <div class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold uppercase tracking-widest text-blue-600">Histori sensor</p><h1 class="mt-1 text-2xl font-bold text-slate-900">Data Logger</h1><p class="mt-1 text-sm text-slate-500">Satu baris per sumber data dan interval penyimpanan.</p></div>
            <a href="{{ route('datalogger.export', $filters) }}" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Unduh CSV</a>
        </div>

        <form method="GET" action="{{ route('datalogger.index') }}" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
            <label class="text-xs font-medium text-slate-600">Gateway<select name="device_id" id="filter-device" class="mt-1 block w-full rounded-lg border-slate-200 text-sm"><option value="">Semua gateway</option>@foreach($devices as $device)<option value="{{ $device->id }}" @selected(($filters['device_id'] ?? '') == $device->id)>{{ $device->device_tag }}</option>@endforeach</select></label>
            <label class="text-xs font-medium text-slate-600">Sumber data<select name="source_id" id="filter-source" class="mt-1 block w-full rounded-lg border-slate-200 text-sm"><option value="">Semua sumber</option>@foreach($devices as $device)@foreach($device->sources as $source)<option value="{{ $source->id }}" data-device-id="{{ $device->id }}" @selected(($filters['source_id'] ?? '') == $source->id)>{{ $device->device_tag }} · {{ $source->name }}</option>@endforeach @endforeach</select></label>
            <label class="text-xs font-medium text-slate-600">Dari<input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-lg border-slate-200 text-sm"></label>
            <label class="text-xs font-medium text-slate-600">Sampai<input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-lg border-slate-200 text-sm"></label>
            <div class="flex items-end gap-2"><button class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white">Terapkan</button><a href="{{ route('datalogger.index') }}" class="rounded-lg border border-slate-200 px-3 py-2.5 text-sm text-slate-500">Reset</a></div>
        </form>

        @if($errors->any())<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-5 py-4"><h2 class="text-sm font-semibold text-slate-900">Interval tersimpan</h2><p class="mt-0.5 text-xs text-slate-500">Rata-rata dihitung dari sampel berkualitas baik. Nilai minimum dan maksimum tersedia pada detail setiap interval.</p></div>
            @forelse($logs as $interval)
                <details class="group border-b border-slate-100 px-5 py-4 last:border-0">
                    <summary class="grid cursor-pointer list-none gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto_auto] sm:items-center">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-slate-900">{{ $interval->source->device->device_tag }}</p><p class="truncate text-xs text-slate-500">{{ $interval->source->name }}</p></div>
                        <p class="text-xs text-slate-500">{{ $interval->bucket_start->format('d M Y H:i') }}</p>
                        <p class="text-xs text-slate-500">{{ $interval->sample_count }} sampel</p>
                        <span class="text-xs font-medium text-blue-600">Detail &darr;</span>
                    </summary>
                    <div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-xs"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-2">Parameter</th><th class="p-2">Rata-rata</th><th class="p-2">Min</th><th class="p-2">Maks</th><th class="p-2">Terakhir</th><th class="p-2">Kualitas</th></tr></thead><tbody>
                        @foreach($interval->metrics as $key => $metric)
                            @php($parameter = $interval->source->parameters->firstWhere('key', $key))
                            @php($unit = $parameter?->unit ?? '')
                            <tr class="border-t border-slate-100"><td class="p-2 font-medium">{{ $parameter?->label ?? $key }}</td><td class="p-2">{{ ($metric['count'] ?? 0) > 0 ? number_format($metric['sum'] / $metric['count'], 2, ',', '.') : '—' }} {{ $unit }}</td><td class="p-2">{{ isset($metric['min']) ? number_format($metric['min'], 2, ',', '.') : '—' }}</td><td class="p-2">{{ isset($metric['max']) ? number_format($metric['max'], 2, ',', '.') : '—' }}</td><td class="p-2">{{ isset($metric['last']) ? number_format($metric['last'], 2, ',', '.') : '—' }}</td><td class="p-2">{{ $metric['quality'] ?? '—' }}</td></tr>
                        @endforeach
                    </tbody></table></div>
                </details>
            @empty
                <div class="px-6 py-14 text-center"><p class="font-medium text-slate-700">Belum ada histori interval</p><p class="mt-1 text-sm text-slate-500">Data akan muncul setelah gateway mengirim MQTT dan interval pertama ditutup.</p></div>
            @endforelse
            @if($logs->hasPages())<div class="border-t border-slate-100 px-5 py-4">{{ $logs->links() }}</div>@endif
        </div>
    </div>
    <script>
        const deviceFilter = document.getElementById('filter-device');
        const sourceFilter = document.getElementById('filter-source');
        deviceFilter?.addEventListener('change', () => {
            for (const option of sourceFilter.options) {
                option.hidden = Boolean(deviceFilter.value && option.dataset.deviceId && option.dataset.deviceId !== deviceFilter.value);
            }
            if (sourceFilter.selectedOptions[0]?.hidden) sourceFilter.value = '';
        });
        deviceFilter?.dispatchEvent(new Event('change'));
    </script>
</x-app-layout>
