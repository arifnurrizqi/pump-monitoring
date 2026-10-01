import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const runtime = window.PumpMonitoringRuntime?.reverb;

if (runtime?.key && runtime?.host) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: runtime.key,
        wsHost: runtime.host,
        wsPort: Number(runtime.port ?? 80),
        wssPort: Number(runtime.port ?? 443),
        forceTLS: (runtime.scheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
