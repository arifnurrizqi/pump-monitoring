{{-- ================================================================
     SIDEBAR NAVIGATION
     This file is included inside <aside> in layouts/app.blade.php
     ================================================================ --}}

{{-- ── Logo ─────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-center px-5 h-14 border-b border-gray-100 shrink-0">
    <img src="{{ asset('images/logo_dev.png') }}" alt="Logo" class="h-10 w-auto object-contain">
</div>

{{-- ── Nav Items ────────────────────────────────────────────────── --}}
<nav class="flex-1 px-3 py-4 space-y-0.5 overflow-y-auto">

    {{-- Dashboard --}}
    <a href="{{ route('dashboard') }}"
       class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
              {{ request()->routeIs('dashboard')
                 ? 'bg-blue-50 text-blue-700'
                 : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        <svg class="w-5 h-5 shrink-0 transition-colors
                    {{ request()->routeIs('dashboard') ? 'fill-blue-600' : 'fill-gray-400 group-hover:fill-gray-600' }}"
             viewBox="0 0 24 24">
            <rect x="2"  y="2"  width="9"  height="11" rx="2"/>
            <rect x="13" y="2"  width="9"  height="7"  rx="2"/>
            <rect x="2"  y="15" width="9"  height="7"  rx="2"/>
            <rect x="13" y="11" width="9"  height="11" rx="2"/>
        </svg>
        <span>Dashboard</span>

        @if(request()->routeIs('dashboard'))
            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-600"></span>
        @endif
    </a>

    {{-- Data Logger --}}
    <a href="{{ route('datalogger.index') }}"
       class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
              {{ request()->routeIs('datalogger.*')
                 ? 'bg-blue-50 text-blue-700'
                 : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        <svg class="w-5 h-5 shrink-0 transition-colors
                    {{ request()->routeIs('datalogger.*') ? 'fill-blue-600' : 'fill-gray-400 group-hover:fill-gray-600' }}"
             viewBox="3 3 20 20">
            <path d="M21 9.5v3c0 2.485-4.03 4.5-9 4.5s-9-2.015-9-4.5v-3c0 2.485 4.03 4.5 9 4.5s9-2.015 9-4.5z
                     M3 14.5c0 2.485 4.03 4.5 9 4.5s9-2.015 9-4.5v3c0 2.485-4.03 4.5-9 4.5s-9-2.015-9-4.5v-3z
                     M12 12c-4.97 0-9-2.015-9-4.5S7.03 3 12 3s9 2.015 9 4.5-4.03 4.5-9 4.5z"/>
        </svg>
        <span>Data Logger</span>

        @if(request()->routeIs('datalogger.*'))
            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-600"></span>
        @endif
    </a>

    {{-- Alarm --}}
    <a href="{{ route('alarms.index') }}"
       class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
              {{ request()->routeIs('alarms.*')
                 ? 'bg-blue-50 text-blue-700'
                 : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        <svg class="w-5 h-5 shrink-0 transition-colors
                    {{ request()->routeIs('alarms.*') ? 'fill-blue-600' : 'fill-gray-400 group-hover:fill-gray-600' }}"
             viewBox="0 0 24 24">
            <path fill-rule="evenodd" clip-rule="evenodd"
                  d="M12 2a7 7 0 0 0-7 7v3.586l-1.707 1.707A1 1 0 0 0 4 16h16a1 1 0
                     0 0 .707-1.707L19 12.586V9a7 7 0 0 0-7-7zm0 18a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3z"/>
        </svg>
        <span>Alarm</span>

        <span
            data-realtime-sidebar-alarm
            data-alarm-count="{{ (int) ($navigationActiveAlarmCount ?? 0) }}"
            class="relative ml-auto flex h-5 min-w-5 items-center justify-center {{ ($navigationActiveAlarmCount ?? 0) > 0 ? '' : 'hidden' }}"
            title="{{ $navigationActiveAlarmCount ?? 0 }} active alarm"
        >
            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-60"></span>
            <span data-realtime-sidebar-alarm-count class="relative inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold leading-none text-white">
                {{ ($navigationActiveAlarmCount ?? 0) > 99 ? '99+' : ($navigationActiveAlarmCount ?? 0) }}
            </span>
        </span>
        @if(request()->routeIs('alarms.*'))
            <span data-realtime-sidebar-alarm-empty class="ml-auto h-1.5 w-1.5 rounded-full bg-blue-600 {{ ($navigationActiveAlarmCount ?? 0) > 0 ? 'hidden' : '' }}"></span>
        @endif
    </a>

    {{-- Devices --}}
    <a href="{{ route('devices.index') }}"
       class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
              {{ request()->routeIs('devices.*')
                 ? 'bg-blue-50 text-blue-700'
                 : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        <svg class="w-5 h-5 shrink-0 transition-colors
                    {{ request()->routeIs('devices.*') ? 'fill-blue-600' : 'fill-gray-400 group-hover:fill-gray-600' }}"
             viewBox="0 0 48 48">
            <rect width="48" height="48" fill="none"/>
            <path d="M2 32v10a2 2 0 0 0 2 2h40a2 2 0 0 0 2-2V32zm30 6a2 2 0 1 1 2-2 2 2 0 0 1-2 2zm6 0a2 2 0 1
                     1 2-2 2 2 0 0 1-2 2zM44 4H4a2 2 0 0 0-2 2v10h44V6a2 2 0 0 0-2-2zm-12 10a2 2 0 1 1
                     2-2 2 2 0 0 1-2 2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2zM2 20v8h44v-8zm30 6a2 2 0 1 1 2-2
                     2 2 0 0 1-2 2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2z"/>
        </svg>
        <span>Devices</span>

        @if(request()->routeIs('devices.*'))
            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-600"></span>
        @endif
    </a>
    {{-- Settings --}}
    <a href="{{ route('profile.edit') }}"
       class="group flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors
              {{ request()->routeIs('profile.*')
                 ? 'bg-blue-50 text-blue-700'
                 : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
        <svg class="w-5 h-5 shrink-0 transition-colors
                    {{ request()->routeIs('profile.*') ? 'fill-blue-600' : 'fill-gray-400 group-hover:fill-gray-600' }}"
             viewBox="0 0 24 24">
            <path fill-rule="evenodd" clip-rule="evenodd"
                  d="M12 1a2.5 2.5 0 0 0-2.33 1.625l-.28.757a7.67 7.67 0 0 0-1.256.726l-.795-.2A2.5 2.5
                     0 0 0 4.61 5.11l-.974 1.688a2.5 2.5 0 0 0 .418 3.086l.57.538a7.743 7.743 0 0 0 0
                     1.156l-.57.538a2.5 2.5 0 0 0-.418 3.086l.974 1.688a2.5 2.5 0 0 0 2.729 1.202l.795-.2c.4.27.82.505
                     1.256.726l.28.757A2.5 2.5 0 0 0 12 21.001a2.5 2.5 0 0 0 2.33-1.625l.28-.757a7.67 7.67 0 0 0
                     1.256-.726l.795.2a2.5 2.5 0 0 0 2.729-1.202l.974-1.688a2.5 2.5 0 0 0-.418-3.086l-.57-.538a7.743
                     7.743 0 0 0 0-1.156l.57-.538a2.5 2.5 0 0 0 .418-3.086l-.974-1.688a2.5 2.5 0 0 0-2.729-1.202l-.795.2a7.67
                     7.67 0 0 0-1.256-.726l-.28-.757A2.5 2.5 0 0 0 12 1zm0 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
        </svg>
        <span>Settings</span>

        @if(request()->routeIs('profile.*'))
            <span class="ml-auto w-1.5 h-1.5 rounded-full bg-blue-600"></span>
        @endif
    </a>

</nav>

{{-- ── Footer: User + Logout ────────────────────────────────────── --}}
<div class="px-3 py-4 border-t border-gray-100 shrink-0 space-y-1">

    {{-- Logged-in user --}}
    @auth

    {{-- Logout --}}
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit"
                class="group w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium
                       text-gray-600 hover:bg-red-50 hover:text-red-600 transition-colors">
            <svg class="w-5 h-5 shrink-0 fill-gray-400 group-hover:fill-red-500 transition-colors"
                 viewBox="0 0 24 24">
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M16.125 12a.75.75 0 0 0-.75-.75H4.402l1.961-1.681a.75.75 0 0
                         0-1.026-1.088l-3.5 3a.75.75 0 0 0 0 1.138l3.5 3a.75.75 0 1 0
                         .976-1.138L4.402 12.75H15.375a.75.75 0 0 0 .75-.75z"/>
                <path d="M9.375 8c0 .702 0 1.053.168 1.306a1 1 0 0 0 .276.274C10.072 9.75
                         10.423 9.75 11.125 9.75H15.375a2.625 2.625 0 1 1 0 5.25H11.125c-.702
                         0-1.053 0-1.306.17a1 1 0 0 0-.276.274C9.375 15.697 9.375 16.048 9.375
                         16.75c0 2.828 0 4.243.879 5.121C11.132 22.75 12.546 22.75 15.375
                         22.75h1c2.828 0 4.243 0 5.121-.879.879-.878.879-2.293.879-5.121V8c0-2.828
                         0-4.243-.879-5.121C20.618 2 19.203 2 16.375 2h-1c-2.829 0-4.243
                         0-5.121.879C9.375 3.757 9.375 5.172 9.375 8z"/>
            </svg>
            Logout
        </button>
    </form>
    @endauth

</div>
