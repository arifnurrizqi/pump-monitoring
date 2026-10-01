<?php

return [
    'host' => env('MQTT_HOST', ''),
    'port' => (int) env('MQTT_PORT', 8883),
    'client_id_prefix' => env('MQTT_CLIENT_ID_PREFIX', 'pump-monitoring'),
    'username' => env('MQTT_USERNAME'),
    'password' => env('MQTT_PASSWORD'),
    'clean_session' => (bool) env('MQTT_CLEAN_SESSION', true),

    'connect_timeout' => (int) env('MQTT_CONNECT_TIMEOUT', 5),
    'socket_timeout' => (int) env('MQTT_SOCKET_TIMEOUT', 5),
    'keep_alive' => (int) env('MQTT_KEEP_ALIVE', 60),
    'telemetry_topic' => env('MQTT_TELEMETRY_TOPIC', 'devices/+/telemetry'),
    'telemetry_client_id' => env('MQTT_TELEMETRY_CLIENT_ID', 'pump-telemetry-ingest'),
    'history_interval_seconds' => (int) env('MQTT_HISTORY_INTERVAL_SECONDS', 60),
    'live_cache_store' => env('MQTT_LIVE_CACHE_STORE', 'redis'),

    'tls' => [
        'enabled' => (bool) env('MQTT_TLS_ENABLED', true),
        'verify_peer' => (bool) env('MQTT_TLS_VERIFY_PEER', true),
        'verify_peer_name' => (bool) env('MQTT_TLS_VERIFY_PEER_NAME', true),
        'allow_self_signed' => (bool) env('MQTT_TLS_ALLOW_SELF_SIGNED', false),
        'ca_file' => env('MQTT_TLS_CA_FILE'),
        'client_certificate_file' => env('MQTT_TLS_CLIENT_CERTIFICATE_FILE'),
        'client_certificate_key_file' => env('MQTT_TLS_CLIENT_CERTIFICATE_KEY_FILE'),
        'client_certificate_key_passphrase' => env('MQTT_TLS_CLIENT_CERTIFICATE_KEY_PASSPHRASE'),
    ],

    'last_will' => [
        'topic' => env('MQTT_LAST_WILL_TOPIC', 'dashboard/status'),
        'message' => env('MQTT_LAST_WILL_MESSAGE', 'offline'),
        'qos' => (int) env('MQTT_LAST_WILL_QOS', 1),
        'retain' => (bool) env('MQTT_LAST_WILL_RETAIN', false),
    ],
];
