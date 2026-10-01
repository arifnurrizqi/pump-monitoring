<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ config('app.name', 'Water Analyzer') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @php
        $hydroflowRuntime = [
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key'),
                'host' => config('reverb.public.host') ?: request()->getHost(),
                'port' => (int) (config('reverb.public.port') ?: (request()->isSecure() ? 443 : 80)),
                'scheme' => config('reverb.public.scheme') ?: (request()->isSecure() ? 'https' : 'http'),
            ],
            'version' => config('app.version'),
        ];
    @endphp
    <script>
        window.HydroflowRuntime = {{ Illuminate\Support\Js::from($hydroflowRuntime) }};
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-gray-50 text-gray-900">

    <div class="flex h-screen overflow-hidden">

        {{-- ===================== SIDEBAR (Desktop Only) ===================== --}}
        <aside class="hidden lg:flex flex-col w-64 bg-white border-r border-gray-200 shrink-0">
            @include('layouts.navigation')
        </aside>

        {{-- =================== MAIN AREA ==================== --}}
        <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

            {{-- Top Bar --}}
            <header class="flex items-center justify-between h-14 px-4 lg:px-6 bg-white border-b border-gray-200 shrink-0">

                {{-- Left: app header --}}
                <div class="flex items-center gap-3 min-w-0">
                    <div class="min-w-0">
                        <h1 class="text-lg font-bold text-gray-900 truncate leading-tight">
                            {{ config('app.name') }}
                        </h1>
                        <p class="text-[10px] text-gray-400 truncate leading-tight">
                            {{ config('app.description') }}
                        </p>
                    </div>
                </div>

                <!-- Client Logo & User Section (Responsive) -->
                <div class="flex items-center gap-4 pl-3">
                    <div data-realtime-connection class="hidden items-center gap-1.5 text-[10px] font-semibold text-gray-500 sm:flex" title="WebSocket connection status">
                        <span data-realtime-connection-dot class="h-2 w-2 rounded-full bg-amber-400"></span>
                        <span data-realtime-connection-label>Connecting</span>
                    </div>

                    <!-- Client Logo -->
                    <div class="hidden sm:flex items-center flex-shrink-0 h-10">
                        <img src="{{ asset('images/logo_user.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                    </div>

                    <!-- Vertical Divider -->
                    <div class="hidden sm:block h-8 w-px bg-gray-200"></div>

                    <!-- User Profile -->
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-sm font-bold text-blue-700">{{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 2)) }}</span>
                        </div>
                        <div class="hidden md:block min-w-0">
                            <p class="text-sm font-bold text-gray-800 leading-tight">{{ auth()->user()->name }}</p>
                            <p class="text-[10px] text-gray-400 font-medium uppercase tracking-wide">{{ auth()->user()->role ?? 'Staff' }}</p>
                        </div>
                        
                        <!-- Simple Logout Icon Button for Mobile/Tablet -->
                        <form method="POST" action="{{ route('logout') }}" class="lg:hidden">
                            @csrf
                            <button type="submit" class="p-1.5 rounded-lg text-gray-400 hover:text-red-500 transition-colors" title="Logout">
                                <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24">
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
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            {{-- Page Content --}}
            <main class="flex-1 overflow-y-auto bg-gray-50 pb-20 lg:pb-0">
                <div class="px-4 lg:px-6 xl:px-8 pt-4 pb-4 lg:pb-6 xl:pb-8">
                    {{ $slot }}
                </div>
            </main>

        </div>{{-- /main area --}}
    </div>{{-- /flex --}}

    {{-- Bottom Bar for Mobile/Tablet --}}
    <div class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 lg:hidden flex justify-around items-center h-16 pb-safe">
        {{-- Dashboard --}}
        <a href="{{ route('dashboard') }}" 
           class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-colors
                  {{ request()->routeIs('dashboard') ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
            <svg class="w-5 h-5 transition-colors {{ request()->routeIs('dashboard') ? 'fill-blue-600' : 'fill-gray-400' }}" viewBox="0 0 24 24">
                <rect x="2"  y="2"  width="9"  height="11" rx="2"/>
                <rect x="13" y="2"  width="9"  height="7"  rx="2"/>
                <rect x="2"  y="15" width="9"  height="7"  rx="2"/>
                <rect x="13" y="11" width="9"  height="11" rx="2"/>
            </svg>
            <span class="text-[10px] font-semibold mt-1">Dashboard</span>
        </a>

        {{-- Data Logger --}}
        <a href="{{ route('datalogger.index') }}" 
           class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-colors
                  {{ request()->routeIs('datalogger.*') ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
            <svg class="w-5 h-5 transition-colors {{ request()->routeIs('datalogger.*') ? 'fill-blue-600' : 'fill-gray-400' }}" viewBox="3 3 20 20">
                <path d="M21 9.5v3c0 2.485-4.03 4.5-9 4.5s-9-2.015-9-4.5v-3c0 2.485 4.03 4.5 9 4.5s9-2.015 9-4.5z
                         M3 14.5c0 2.485 4.03 4.5 9 4.5s9-2.015 9-4.5v3c0 2.485-4.03 4.5-9 4.5s-9-2.015-9-4.5v-3z
                         M12 12c-4.97 0-9-2.015-9-4.5S7.03 3 12 3s9 2.015 9 4.5-4.03 4.5-9 4.5z"/>
            </svg>
            <span class="text-[10px] font-semibold mt-1">Logs</span>
        </a>

        {{-- Alarm --}}
        <a href="{{ route('alarms.index') }}" 
           class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-colors
                  {{ request()->routeIs('alarms.*') ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
            <span class="relative">
                <svg class="w-5 h-5 transition-colors {{ request()->routeIs('alarms.*') ? 'fill-blue-600' : 'fill-gray-400' }}" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" clip-rule="evenodd"
                          d="M12 2a7 7 0 0 0-7 7v3.586l-1.707 1.707A1 1 0 0 0 4 16h16a1 1 0 0 0 .707-1.707L19 12.586V9a7 7 0 0 0-7-7zm0 18a3 3 0 0 1-3-3h6a3 3 0 0 1-3 3z"/>
                </svg>
                <span
                    data-realtime-sidebar-alarm
                    data-alarm-count="{{ (int) ($navigationActiveAlarmCount ?? 0) }}"
                    class="absolute -right-1 -top-1 flex h-2.5 w-2.5 {{ ($navigationActiveAlarmCount ?? 0) > 0 ? '' : 'hidden' }}"
                    title="{{ $navigationActiveAlarmCount ?? 0 }} active alarm"
                >
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-red-600"></span>
                </span>
            </span>
            <span class="text-[10px] font-semibold mt-1">Alarm</span>
        </a>

        {{-- Devices --}}
        <a href="{{ route('devices.index') }}" 
           class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-colors
                  {{ request()->routeIs('devices.*') ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
            <svg class="w-5 h-5 transition-colors {{ request()->routeIs('devices.*') ? 'fill-blue-600' : 'fill-gray-400' }}" viewBox="0 0 48 48">
                <rect width="48" height="48" fill="none"/>
                <path d="M2 32v10a2 2 0 0 0 2 2h40a2 2 0 0 0 2-2V32zm30 6a2 2 0 1 1 2-2 2 2 0 0 1-2 2zm6 0a2 2 0 1
                         1 2-2 2 2 0 0 1-2 2zM44 4H4a2 2 0 0 0-2 2v10h44V6a2 2 0 0 0-2-2zm-12 10a2 2 0 1 1
                         2-2 2 2 0 0 1-2 2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2zM2 20v8h44v-8zm30 6a2 2 0 1 1 2-2
                         2 2 0 0 1-2 2zm6 0a2 2 0 1 1 2-2 2 2 0 0 1-2 2z"/>
            </svg>
            <span class="text-[10px] font-semibold mt-1">Devices</span>
        </a>

        {{-- Settings --}}
        <a href="{{ route('profile.edit') }}" 
           class="flex flex-col items-center justify-center flex-1 h-full py-1 text-center transition-colors
                  {{ request()->routeIs('profile.*') ? 'text-blue-600' : 'text-gray-500 hover:text-gray-700' }}">
            <svg class="w-5 h-5 transition-colors {{ request()->routeIs('profile.*') ? 'fill-blue-600' : 'fill-gray-400' }}" viewBox="0 0 24 24">
                <path fill-rule="evenodd" clip-rule="evenodd"
                      d="M12 1a2.5 2.5 0 0 0-2.33 1.625l-.28.757a7.67 7.67 0 0 0-1.256.726l-.795-.2A2.5 2.5
                         0 0 0 4.61 5.11l-.974 1.688a2.5 2.5 0 0 0 .418 3.086l.57.538a7.743 7.743 0 0 0 0
                         1.156l-.57.538a2.5 2.5 0 0 0-.418 3.086l.974 1.688a2.5 2.5 0 0 0 2.729 1.202l.795-.2c.4.27.82.505
                         1.256.726l.28.757A2.5 2.5 0 0 0 12 21.001a2.5 2.5 0 0 0 2.33-1.625l.28-.757a7.67 7.67 0 0 0
                         1.256-.726l.795.2a2.5 2.5 0 0 0 2.729-1.202l.974-1.688a2.5 2.5 0 0 0-.418-3.086l-.57-.538a7.743
                         7.743 0 0 0 0-1.156l.57-.538a2.5 2.5 0 0 0 .418-3.086l-.974-1.688a2.5 2.5 0 0 0-2.729-1.202l-.795.2a7.67
                         7.67 0 0 0-1.256-.726l-.28-.757A2.5 2.5 0 0 0 12 1zm0 7a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>
            </svg>
            <span class="text-[10px] font-semibold mt-1">Settings</span>
        </a>
    </div>

    @stack('scripts')
</body>

</html>
