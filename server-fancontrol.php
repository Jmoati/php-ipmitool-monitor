<?php

// Based on following solution: https://askubuntu.com/a/1312802/1067698
// This code is for the following server configuration:
// Dell R610, 24 x Intel(R) Xeon(R) CPU X5690 @ 3.47GHz (2 Sockets)

// This code must be modified according TO YOUR OWN server configuration!

// Make sure you have installed "ipmitools" and you can see the output of command "sensors"

// USE AT YOUR OWN RISK
// E-Mail: fred@coldrocksoftware.com

const MIN_FAN = 5;
const MAX_FAN = 100;

const MIN_TEMP = 45; # adjust based on sensors output
const MAX_TEMP = 75; # adjust based on sensors output

const TEMP_POW = 3; # decrease for cooler server, increase for quiter

system('clear');

$sensorsPath = trim(shell_exec('which sensors'));
$sensorData = trim(shell_exec($sensorsPath . ' -j'));
$decodedData = json_decode(trim($sensorData), true);

$coreTemperatures = [];

foreach ($decodedData as $core => $coreData) {
    foreach ($coreData as $coreItem => $coreItemData) {
        if (stristr($coreItem, 'Adapter')) {
            continue;
        }

        foreach ($coreItemData as $index => $value) {
            if (!stristr($index, '_input')) {
                continue;
            }

            $coreTemperatures[$core][] = $value;
        }
    }
}

$averages = [];
foreach ($coreTemperatures as $module => $temperatures) {
    $a = array_filter($temperatures);
    $average = array_sum($a)/count($a);
    $averages[$module] = ceil($average);
}

$finalRawAverages = array_values($averages);
$finalAverage = ceil(array_sum($finalRawAverages)/count($finalRawAverages));

$x = min(1, max(0, ($finalAverage - MIN_TEMP) / (MAX_TEMP - MIN_TEMP)));
$fanLevel = (int)min(MAX_FAN, max(MIN_FAN, pow($x, TEMP_POW)*(MAX_FAN-MIN_FAN) + MIN_FAN));
$fanLevelHex = '0x' .dechex($fanLevel);

echo 'System average temperature: ' . $finalAverage . PHP_EOL;
echo 'Fan level to set: ' . $fanLevel . PHP_EOL;
echo 'Fan level hex: ' . $fanLevelHex . PHP_EOL;

shell_exec("ipmitool raw 0x30 0x30 0x01 0x00");
shell_exec("ipmitool raw 0x30 0x30 0x02 0xff {$fanLevelHex}");

echo "Fan speed was set to the new levels" . PHP_EOL;