<x-app-layout>
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('devices.show', $device) }}" class="text-sm text-blue-600">&larr; {{ $device->device_tag }}</a>
                <h1 class="text-2xl font-bold text-gray-900 mt-2">Konfigurasi monitoring</h1>
                <p class="text-sm text-gray-500 mt-1">Satu gateway dapat membaca beberapa sumber data dan parameter.</p>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-sm text-red-700">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        @if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('success') }}</div>@endif

        @if($canEdit)
        <form method="POST" action="{{ route('devices.monitoring.gateway', $device) }}" class="flex flex-wrap items-end gap-3 rounded-2xl border bg-white p-5">
            @csrf @method('PATCH')
            <label class="text-sm font-medium text-gray-700">Interval kirim gateway (detik)<input type="number" name="send_interval_sec" min="1" max="3600" required value="{{ old('send_interval_sec', $device->setting?->send_interval_sec ?? 10) }}" class="mt-1 block w-40 rounded-lg border-gray-300 text-sm"></label>
            <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white">Simpan interval</button>
            <p class="w-full text-xs text-gray-500">Dipakai untuk menentukan kapan gateway dianggap terlambat atau offline.</p>
        </form>
        @endif

        @if(!$device->site && $canEdit)
            @if($availableSites->isNotEmpty())
            <form method="POST" action="{{ route('devices.monitoring.site', $device) }}" class="bg-white rounded-2xl border p-5 space-y-3">
                @csrf
                <h2 class="font-semibold">Gunakan lokasi produksi yang ada</h2>
                <select name="existing_site_id" required class="rounded-lg border-gray-300 text-sm">
                    @foreach($availableSites as $site)<option value="{{ $site->id }}">{{ $site->name }} ({{ $site->code }})</option>@endforeach
                </select>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Pilih lokasi</button>
            </form>
            @endif
            <form method="POST" action="{{ route('devices.monitoring.site', $device) }}" class="bg-white rounded-2xl border p-5 space-y-3">
                @csrf
                <h2 class="font-semibold">1. Lokasi produksi</h2>
                <div class="grid sm:grid-cols-3 gap-3">
                    <input name="code" value="{{ old('code') }}" required placeholder="Kode, contoh SUMUR-A" class="rounded-lg border-gray-300 text-sm">
                    <input name="name" value="{{ old('name') }}" required placeholder="Nama lokasi" class="rounded-lg border-gray-300 text-sm">
                    <input name="location" value="{{ old('location') }}" placeholder="Alamat atau koordinat" class="rounded-lg border-gray-300 text-sm">
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Simpan lokasi</button>
            </form>
        @elseif($device->site)
            <div class="bg-white rounded-2xl border p-5">
                <p class="text-xs text-gray-500">Lokasi produksi</p>
                <p class="font-semibold">{{ $device->site->name }} <span class="text-gray-400 font-normal">({{ $device->site->code }})</span></p>
            </div>
            @if($canEdit)
            <form method="POST" action="{{ route('devices.monitoring.asset', $device) }}" class="bg-white rounded-2xl border p-5 space-y-3">
                @csrf
                <h2 class="font-semibold">2. Tambah aset</h2>
                <div class="grid sm:grid-cols-3 gap-3">
                    <input name="name" required placeholder="Contoh Pompa sumur 1" class="rounded-lg border-gray-300 text-sm">
                    <select name="type" class="rounded-lg border-gray-300 text-sm"><option value="pump">Pompa</option><option value="motor">Motor</option><option value="other">Lainnya</option></select>
                    <select name="phase_count" class="rounded-lg border-gray-300 text-sm"><option value="">Fasa tidak berlaku</option><option value="1">1 fasa</option><option value="3">3 fasa</option></select>
                </div>
                <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Tambah aset</button>
            </form>
            @endif
        @endif

        @if($canEdit)
        <form method="POST" action="{{ route('devices.monitoring.source', $device) }}" class="bg-white rounded-2xl border p-5 space-y-3">
            @csrf
            <h2 class="font-semibold">3. Tambah sumber data</h2>
            <p class="text-xs text-gray-500">Kunci sumber harus sama dengan field <code>source</code> pada payload gateway. Power meter mendapat parameter dasar sesuai jumlah fasa aset.</p>
            <div class="grid sm:grid-cols-2 gap-3">
                <input name="source_key" required placeholder="Kunci, contoh PM-01" class="rounded-lg border-gray-300 text-sm">
                <input name="name" required placeholder="Nama, contoh Power Meter Pompa 1" class="rounded-lg border-gray-300 text-sm">
                <select name="type" class="rounded-lg border-gray-300 text-sm"><option value="power_meter">Power meter</option><option value="flow">Flow</option><option value="pressure">Tekanan</option><option value="temperature">Temperatur</option><option value="other">Lainnya</option></select>
                <select name="monitored_asset_id" class="rounded-lg border-gray-300 text-sm">
                    <option value="">Tanpa aset</option>
                    @foreach($device->site?->assets ?? [] as $asset)
                        <option value="{{ $asset->id }}">{{ $asset->name }}{{ $asset->phase_count ? ' · '.$asset->phase_count.' fasa' : '' }}</option>
                    @endforeach
                </select>
            </div>
            <button class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Tambah sumber</button>
        </form>
        @endif

        @foreach($device->sources as $source)
            <div class="bg-white rounded-2xl border p-5 space-y-4">
                <div>
                    <h2 class="font-semibold">{{ $source->name }} <span class="text-sm font-normal text-gray-500">{{ $source->source_key }}</span></h2>
                    <p class="text-xs text-gray-500">{{ $source->asset?->name ?? 'Tanpa aset' }} · {{ match($source->type) { 'power_meter' => 'Power meter', 'temperature' => 'Temperatur', 'pressure' => 'Tekanan', 'flow' => 'Debit', default => 'Sensor lain' } }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm"><thead><tr class="text-left text-gray-500"><th class="p-2">Parameter</th><th class="p-2">Satuan</th><th class="p-2">Alarm minimum</th><th class="p-2">Alarm maksimum</th><th class="p-2">Aktif</th><th class="p-2"></th></tr></thead>
                        <tbody>
                        @foreach($source->parameters as $parameter)
                            @if($canEdit)
                            <tr class="border-t">
                                <td class="p-2"><form id="parameter-{{ $parameter->id }}" method="POST" action="{{ route('devices.monitoring.parameter.update', [$device, $source, $parameter]) }}">@csrf @method('PATCH')<input name="label" value="{{ $parameter->label }}" required class="w-40 rounded-lg border-gray-300 text-sm"></form><span class="block text-xs text-gray-400">{{ $parameter->key }}</span></td>
                                <td class="p-2"><input form="parameter-{{ $parameter->id }}" name="unit" value="{{ $parameter->unit }}" class="w-20 rounded-lg border-gray-300 text-sm"></td>
                                <td class="p-2"><input form="parameter-{{ $parameter->id }}" name="alarm_min" type="number" step="any" value="{{ $parameter->alarm_min }}" class="w-28 rounded-lg border-gray-300 text-sm"></td>
                                <td class="p-2"><input form="parameter-{{ $parameter->id }}" name="alarm_max" type="number" step="any" value="{{ $parameter->alarm_max }}" class="w-28 rounded-lg border-gray-300 text-sm"></td>
                                <td class="p-2"><input form="parameter-{{ $parameter->id }}" type="checkbox" name="alarm_enabled" value="1" @checked($parameter->alarm_enabled)></td>
                                <td class="p-2"><button form="parameter-{{ $parameter->id }}" class="text-blue-600">Simpan</button></td>
                            </tr>
                            @else
                            <tr class="border-t"><td class="p-2">{{ $parameter->label }} <span class="text-xs text-gray-400">{{ $parameter->key }}</span></td><td class="p-2">{{ $parameter->unit }}</td><td class="p-2">{{ $parameter->alarm_min ?? '—' }}</td><td class="p-2">{{ $parameter->alarm_max ?? '—' }}</td><td class="p-2">{{ $parameter->alarm_enabled ? 'Ya' : 'Tidak' }}</td><td></td></tr>
                            @endif
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @if($canEdit)
                <form method="POST" action="{{ route('devices.monitoring.parameter', [$device, $source]) }}" class="grid sm:grid-cols-6 gap-2 items-end border-t pt-4">
                    @csrf
                    <input name="key" required placeholder="Kunci, contoh motor_temp" class="rounded-lg border-gray-300 text-sm">
                    <input name="label" required placeholder="Label" class="rounded-lg border-gray-300 text-sm">
                    <input name="unit" placeholder="Satuan" class="rounded-lg border-gray-300 text-sm">
                    <input name="alarm_min" type="number" step="any" placeholder="Min" class="rounded-lg border-gray-300 text-sm">
                    <input name="alarm_max" type="number" step="any" placeholder="Max" class="rounded-lg border-gray-300 text-sm">
                    <label class="text-xs flex items-center gap-2"><input type="checkbox" name="alarm_enabled" value="1"> Alarm aktif</label>
                    <button class="sm:col-span-6 justify-self-start px-4 py-2 bg-gray-800 text-white rounded-lg text-sm">Tambah parameter</button>
                </form>
                @endif
            </div>
        @endforeach
    </div>
</x-app-layout>
