<?php

// Based on following solution: https://askubuntu.com/a/1312802/1067698
// This code is for the following server configuration:
// Dell PowerEdge R730xd, 36 x Intel(R) Xeon(R) CPU E5-2699 v3 @ 2.30GHz (2 Sockets)

// This code must be modified according TO YOUR OWN server configuration!
// USE AT YOUR OWN RISK

const MIN_FAN = 10;
const MAX_FAN = 100;
const MIN_TEMP = 50;
const MAX_TEMP = 80;
const TEMP_POW = 3; # decrease for cooler server, increase for quiter

if (
    !array_key_exists('IPMI_IP', $_ENV)
    && !array_key_exists('IPMI_LOGIN', $_ENV)
    && !array_key_exists('IPMI_PASS', $_ENV)
) {
    if (!file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.env')) {
        throw new Exception('Please, create a .env file with IPMI_IP, IPMI_LOGIN and IPMI_PASS');
    }

    $_ENV = array_merge($_ENV, parse_ini_file('.env'));
}

$ipmitool = sprintf(
    'ipmitool -I lanplus -H %s  -U %s -P %s',
    $_ENV['IPMI_IP'],
    $_ENV['IPMI_LOGIN'],
    $_ENV['IPMI_PASS']
);

while(1) {
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
    $fanLevelHex = '0x' .dechex($fanLevel);

    shell_exec(sprintf('%s raw 0x30 0x30 0x01 0x00', $ipmitool));
    shell_exec(sprintf('%s raw 0x30 0x30 0x02 0xff %s', $ipmitool, $fanLevelHex));

    echo sprintf('Fan level: %d%%%s', $fanLevel, PHP_EOL);

    sleep(10);
}