// M5Stack ATOM Lite + Grove BME280 – publishes readings to an MQTT broker
// Board: "ESP32 Dev Module", Upload Speed 115200
// Libraries: "Adafruit BME280 Library" (installed) + "PubSubClient" by Nick O'Leary
//
// Publishes every 5 s to topic  home/<ROOM>/bme280
// Payload: {"temperature":22.70,"humidity":40.09,"pressure":984.88}
// LED: blue = connecting, green = sent, red = problem

#include <WiFi.h>
#include <PubSubClient.h>
#include <Wire.h>
#include <Adafruit_BME280.h>
#include "secrets.h"   // WIFI_NAME and WIFI_PASSWORD – copy secrets.example.h to secrets.h (not committed to git)

// ---------- CHANGE THESE ----------
const char* MQTT_HOST     = "192.168.1.154";     // Mac mini now, Raspberry Pi later
const int   MQTT_PORT     = 1883;
const char* ROOM          = "living-room";       // lowercase letters, numbers and dashes only
// ----------------------------------

#define SDA_PIN 26
#define SCL_PIN 32
#define LED_PIN 27
const unsigned long SEND_INTERVAL_MS = 5000;

Adafruit_BME280 bme;
WiFiClient net;
PubSubClient mqtt(net);
char topic[64];
char clientId[32];
unsigned long lastSend = 0;

void led(uint8_t r, uint8_t g, uint8_t b) { rgbLedWrite(LED_PIN, r / 4, g / 4, b / 4); }

void connectWiFi() {
  if (WiFi.status() == WL_CONNECTED) return;
  led(0, 0, 255);
  Serial.printf("Connecting to WiFi '%s'", WIFI_NAME);
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_NAME, WIFI_PASSWORD);
  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < 15000) {
    delay(500);
    Serial.print(".");
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("\nWiFi connected, ATOM IP: %s\n", WiFi.localIP().toString().c_str());
  } else {
    Serial.println("\nWiFi failed, will retry");
  }
}

void connectMqtt() {
  if (mqtt.connected() || WiFi.status() != WL_CONNECTED) return;
  led(0, 0, 255);
  Serial.printf("Connecting to MQTT %s:%d as %s... ", MQTT_HOST, MQTT_PORT, clientId);
  if (mqtt.connect(clientId)) {
    Serial.println("connected");
  } else {
    // -2 = can't reach broker (wrong IP, broker not running, firewall)
    Serial.printf("failed (state %d), will retry\n", mqtt.state());
  }
}

void setup() {
  Serial.begin(115200);
  delay(500);
  Wire.begin(SDA_PIN, SCL_PIN);

  if (!bme.begin(0x76, &Wire) && !bme.begin(0x77, &Wire)) {
    Serial.println("ERROR: BME280 not found. Check the Grove cable.");
    led(255, 0, 0);
    while (true) delay(1000);
  }

  snprintf(topic, sizeof(topic), "home/%s/bme280", ROOM);
  snprintf(clientId, sizeof(clientId), "atom-%llx", ESP.getEfuseMac());  // unique per device
  mqtt.setServer(MQTT_HOST, MQTT_PORT);

  connectWiFi();
  connectMqtt();
}

void loop() {
  mqtt.loop();  // keeps the MQTT connection alive

  if (millis() - lastSend < SEND_INTERVAL_MS) return;
  lastSend = millis();

  float t = bme.readTemperature();
  float h = bme.readHumidity();
  float p = bme.readPressure() / 100.0F;

  connectWiFi();
  connectMqtt();
  if (!mqtt.connected()) { led(255, 0, 0); return; }

  char payload[128];
  snprintf(payload, sizeof(payload),
           "{\"temperature\":%.2f,\"humidity\":%.2f,\"pressure\":%.2f}", t, h, p);

  if (mqtt.publish(topic, payload)) {
    led(0, 255, 0);
    Serial.printf("%s %s\n", topic, payload);
  } else {
    led(255, 0, 0);
    Serial.println("Publish failed");
  }
}
