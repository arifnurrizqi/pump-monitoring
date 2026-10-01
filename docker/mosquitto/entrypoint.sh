#!/bin/sh
set -eu

if [ -z "${MQTT_BROKER_USERNAME:-}" ] || [ -z "${MQTT_BROKER_PASSWORD:-}" ]; then
    echo "ERROR: MQTT_BROKER_USERNAME and MQTT_BROKER_PASSWORD are required." >&2
    exit 1
fi

# The image can be restarted or rebuilt while its writable layer still contains
# a password file. Remove it first so `-c` behaves consistently on every start.
rm -f /mosquitto/config/password_file
mosquitto_passwd -b -c /mosquitto/config/password_file "$MQTT_BROKER_USERNAME" "$MQTT_BROKER_PASSWORD"
chown mosquitto:mosquitto /mosquitto/config/password_file /mosquitto/data /mosquitto/log
exec /usr/sbin/mosquitto -c /mosquitto/config/mosquitto.conf
