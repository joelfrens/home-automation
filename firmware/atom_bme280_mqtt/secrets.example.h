// Copy this file to secrets.h (same folder) and fill in your details.
// secrets.h is listed in .gitignore so your passwords never end up on GitHub.
#pragma once
const char* WIFI_NAME     = "YourWiFiName";      // must be a 2.4 GHz network
const char* WIFI_PASSWORD = "YourWiFiPassword";

// MQTT login for sensor nodes: user "sensor", password = MQTT_SENSOR_PASSWORD in the repo-root .env
const char* MQTT_USER     = "sensor";
const char* MQTT_PASSWORD = "YourSensorPassword";
