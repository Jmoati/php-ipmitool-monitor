<?php

// Based on following solution: https://askubuntu.com/a/1312802/1067698
// This code is for the following server configuration:
// Dell PowerEdge R730xd, 36 x Intel(R) Xeon(R) CPU E5-2699 v3 @ 2.30GHz (2 Sockets)

// This code must be modified according TO YOUR OWN server configuration!
// USE AT YOUR OWN RISK


require __DIR__ . '/vendor/autoload.php';

use PhpMqtt\Client\MqttClient;
use PhpMqtt\Client\ConnectionSettings;

const MIN_FAN = 10;
const MAX_FAN = 100;
const MIN_TEMP = 50;
const MAX_TEMP = 80;
const TEMP_POW = 3;
const MAX_FAN_AT_NIGHT = 20;
const SLEEP_TIME = 10;


function loadEnvVariables(): void
{
    if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.env')) {
        throw new Exception('Please, create a .env file with IPMI_IP, IPMI_LOGIN, IPMI_PASS, MQTT_BROKER, MQTT_PORT, MQTT_USER and MQTT_PASS');
    }

    $_ENV = array_merge($_ENV, parse_ini_file('.env'));
}

function getIpmitoolCommand(): string
{
    return sprintf(
        'ipmitool -I lanplus -H %s -U %s -P %s',
        $_ENV['IPMI_IP'],
        $_ENV['IPMI_LOGIN'],
        $_ENV['IPMI_PASS']
    );
}

function connectToMqtt(): MqttClient
{
    $client = new MqttClient($_ENV['MQTT_BROKER'], $_ENV['MQTT_PORT'], 'php-mqtt-client');
    $connectionSettings = (new ConnectionSettings)
        ->setUsername($_ENV['MQTT_USER'])
        ->setPassword($_ENV['MQTT_PASS']);

    $client->connect($connectionSettings, true);

    return $client;
}

function publishToMqtt(string $topic, string $message, MqttClient $client): void
{
    $client->publish($topic, $message, MqttClient::QOS_AT_MOST_ONCE);
}

function publishDiscoveryMessages(MqttClient $client): void
{
    $temperatureConfig = json_encode([
        'name' => 'Server Temperature',
        'state_topic' => 'homeassistant/server/temperature',
        'unit_of_measurement' => '°C',
        'value_template' => '{{ value_json.temperature }}'
    ]);

    $inletTempConfig = json_encode([
        'name' => 'Server Inlet Temperature',
        'state_topic' => 'homeassistant/server/inlet_temp',
        'unit_of_measurement' => '°C',
        'value_template' => '{{ value_json.inlet_temp }}'
    ]);

    $exhaustTempConfig = json_encode([
        'name' => 'Server Exhaust Temperature',
        'state_topic' => 'homeassistant/server/exhaust_temp',
        'unit_of_measurement' => '°C',
        'value_template' => '{{ value_json.exhaust_temp }}'
    ]);

    $fanLevelConfig = json_encode([
        'name' => 'Server Fan Level',
        'state_topic' => 'homeassistant/server/fan_level',
        'unit_of_measurement' => '%',
        'value_template' => '{{ value_json.fan_level }}'
    ]);

    publishToMqtt('homeassistant/sensor/server_temperature/config', $temperatureConfig, $client);
    publishToMqtt('homeassistant/sensor/server_inlet_temp/config', $inletTempConfig, $client);
    publishToMqtt('homeassistant/sensor/server_exhaust_temp/config', $exhaustTempConfig, $client);
    publishToMqtt('homeassistant/sensor/server_fan_level/config', $fanLevelConfig, $client);
}

if (
    !array_key_exists('IPMI_IP', $_ENV)
    && !array_key_exists('IPMI_LOGIN', $_ENV)
    && !array_key_exists('IPMI_PASS', $_ENV)
    && !array_key_exists('MQTT_BROKER', $_ENV)
    && !array_key_exists('MQTT_PORT', $_ENV)
    && !array_key_exists('MQTT_USER', $_ENV)
    && !array_key_exists('MQTT_PASS', $_ENV)
) {
    loadEnvVariables();
}

$ipmitool = getIpmitoolCommand();
$client = connectToMqtt();

while (true) {
    publishDiscoveryMessages($client);

    $temperaturesRaw = explode(PHP_EOL, trim(shell_exec(sprintf('%s sensor reading -c "Inlet Temp" "Temp" "Exhaust Temp"', $ipmitool))));
    $temperatures = [];

    foreach ($temperaturesRaw as $temperatureRaw) {
        list($key, $value) = explode(',', $temperatureRaw);
        $temperatures[$key] = (int)$value;
    }

    if (!array_key_exists('Temp', $temperatures)) {
        sleep(1);
        continue;
    }

    $x = min(1, max(0, ($temperatures['Temp'] - MIN_TEMP) / (MAX_TEMP - MIN_TEMP)));
    $fanLevel = (int)min(MAX_FAN, max(MIN_FAN, pow($x, TEMP_POW)*(MAX_FAN-MIN_FAN) + MIN_FAN));

    $currentHour = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('H');
    $isNightTime = $currentHour >= 22 || $currentHour < 8;

    if ($isNightTime && $fanLevel > MAX_FAN_AT_NIGHT) {
        $fanLevel = MAX_FAN_AT_NIGHT;
    }

    $fanLevelHex = '0x' . dechex($fanLevel);

    shell_exec(sprintf('%s raw 0x30 0x30 0x01 0x00', $ipmitool));
    shell_exec(sprintf('%s raw 0x30 0x30 0x02 0xff %s', $ipmitool, $fanLevelHex));

    echo sprintf('Fan level: %d%%%s', $fanLevel, PHP_EOL);

    publishToMqtt('homeassistant/server/temperature', json_encode(['temperature' => $temperatures['Temp']]), $client);
    publishToMqtt('homeassistant/server/inlet_temp', json_encode(['inlet_temp' => $temperatures['Inlet Temp']]), $client);
    publishToMqtt('homeassistant/server/exhaust_temp', json_encode(['exhaust_temp' => $temperatures['Exhaust Temp']]), $client);
    publishToMqtt('homeassistant/server/fan_level', json_encode(['fan_level' => $fanLevel]), $client);

    sleep(SLEEP_TIME);
}
