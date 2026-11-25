#!/bin/sh
# Builds Mosquitto's password file from environment variables (set in the repo-root .env),
# then starts the broker. Runs on every container start, so changing a password in .env
# only needs: docker compose up -d mosquitto (plus updating the client that uses it).
set -eu

: "${MQTT_ADMIN_PASSWORD:?missing: copy .env.example to .env and set the MQTT_*_PASSWORD values}"
: "${MQTT_SENSOR_PASSWORD:?missing in .env}"
: "${MQTT_INGESTION_PASSWORD:?missing in .env}"
: "${MQTT_LARAVEL_PASSWORD:?missing in .env}"

file=/mosquitto/data/passwd
rm -f "$file"
touch "$file"
chmod 0700 "$file"

mosquitto_passwd -b "$file" admin "$MQTT_ADMIN_PASSWORD"
mosquitto_passwd -b "$file" sensor "$MQTT_SENSOR_PASSWORD"
mosquitto_passwd -b "$file" ingestion "$MQTT_INGESTION_PASSWORD"
mosquitto_passwd -b "$file" laravel "$MQTT_LARAVEL_PASSWORD"

chown mosquitto:mosquitto "$file"

# Mosquitto wants the ACL file private and owned by it; the mounted one can't be, so copy it.
cp /mosquitto/config/acl /mosquitto/data/acl
chmod 0700 /mosquitto/data/acl
chown mosquitto:mosquitto /mosquitto/data/acl

exec /usr/sbin/mosquitto -c /mosquitto/config/mosquitto.conf
