@csrf

{{-- Input helper macro (for reuse inside this partial) --}}
@php
    $inp = 'block w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors';
@endphp

<div class="space-y-5">

    {{-- Device SN --}}
    <div>
        <x-input-label for="device_sn" :value="__('Device SN')" />
        <x-text-input
            id="device_sn"
            name="device_sn"
            type="text"
            :value="old('device_sn', $device->device_sn ?? '')"
            :readonly="isset($device)"
            placeholder="e.g. SN-20240001"
            :class="isset($device) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : ''"
        />
        @error('device_sn')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Device Tag --}}
    <div>
        <x-input-label for="device_tag" :value="__('Device Tag')" />
        <x-text-input
            id="device_tag"
            name="device_tag"
            type="text"
            :value="old('device_tag', $device->device_tag ?? '')"
            placeholder="e.g. WA-TANK-01"
        />
        @error('device_tag')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Location --}}
    <div>
        <x-input-label for="location" :value="__('Location')" />
        <x-text-input
            id="location"
            name="location"
            type="text"
            :value="old('location', $device->location ?? '')"
            placeholder="e.g. Building A – Floor 2"
        />
        @error('location')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Description --}}
    <div>
        <x-input-label for="description" :value="__('Description')" />
        <textarea
            id="description"
            name="description"
            rows="3"
            placeholder="Optional notes about this device…"
            class="{{ $inp }}"
        >{{ old('description', $device->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    {{-- Active toggle --}}
    <label class="flex items-center gap-3 cursor-pointer select-none">
        <input
            type="checkbox"
            name="is_active"
            id="is_active"
            value="1"
            {{ old('is_active', $device->is_active ?? true) ? 'checked' : '' }}
            class="w-4 h-4 rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
        >
        <span class="text-sm font-medium text-gray-700">Active device</span>
    </label>

    {{-- Actions --}}
    <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
        <x-primary-button type="submit">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm-4 16H11m2-4H11m4-4H9"/>
            </svg>
            Save Device
        </x-primary-button>
        <x-secondary-button tag="a" href="{{ $backUrl ?? route('devices.index') }}" type="button" onclick="window.location='{{ $backUrl ?? route('devices.index') }}'">
            Cancel
        </x-secondary-button>
    </div>

</div>
