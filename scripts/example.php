<?php
/**
 * Example usage of SalahAPI PHP library
 * 
 * Run this script: php scripts/example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SalahAPI\SalahAPI;
use SalahAPI\Coordinates;
use SalahAPI\CalculationMethod;

echo "===========================================\n";
echo "  SalahAPI PHP - Prayer Times Calculator  \n";
echo "===========================================\n\n";

// Example 1: Basic usage (New York City)
echo "Example 1: Prayer times for New York City\n";
echo "------------------------------------------\n";

$coordinates = new Coordinates(40.7128, -74.0060);
$timezone = new DateTimeZone('America/New_York');
$salahAPI = new SalahAPI($coordinates, null, $timezone);

$prayerTimes = $salahAPI->getPrayerTimes();

echo "Date: " . date('l, F j, Y') . "\n";
echo "Location: New York City (40.7128°N, 74.0060°W)\n";
echo "Method: Muslim World League\n\n";

foreach ($prayerTimes->toArray('g:i A') as $prayer => $time) {
    echo sprintf("%-10s %s\n", ucfirst($prayer) . ':', $time);
}

echo "\n";

// Example 2: Using different calculation method (ISNA)
echo "Example 2: Prayer times for Los Angeles (ISNA Method)\n";
echo "------------------------------------------------------\n";

$laCoordinates = new Coordinates(34.0522, -118.2437);
$laTimezone = new DateTimeZone('America/Los_Angeles');
$isnaMethod = CalculationMethod::fromArray(CalculationMethod::ISLAMIC_SOCIETY_OF_NORTH_AMERICA);
$laSalahAPI = new SalahAPI($laCoordinates, $isnaMethod, $laTimezone);

$laPrayerTimes = $laSalahAPI->getPrayerTimes();

echo "Date: " . date('l, F j, Y') . "\n";
echo "Location: Los Angeles (34.0522°N, 118.2437°W)\n";
echo "Method: Islamic Society of North America (ISNA)\n\n";

foreach ($laPrayerTimes->toArray('g:i A') as $prayer => $time) {
    echo sprintf("%-10s %s\n", ucfirst($prayer) . ':', $time);
}

echo "\n";

// Example 3: Prayer times for Mecca
echo "Example 3: Prayer times for Mecca (Umm al-Qura Method)\n";
echo "-------------------------------------------------------\n";

$meccaCoordinates = new Coordinates(21.4225, 39.8262);
$meccaTimezone = new DateTimeZone('Asia/Riyadh');
$ummAlQuraMethod = CalculationMethod::fromArray(CalculationMethod::UMM_AL_QURA);
$meccaSalahAPI = new SalahAPI($meccaCoordinates, $ummAlQuraMethod, $meccaTimezone);

$meccaPrayerTimes = $meccaSalahAPI->getPrayerTimes();

echo "Date: " . date('l, F j, Y') . "\n";
echo "Location: Mecca (21.4225°N, 39.8262°E)\n";
echo "Method: Umm al-Qura University, Makkah\n\n";

foreach ($meccaPrayerTimes->toArray('g:i A') as $prayer => $time) {
    echo sprintf("%-10s %s\n", ucfirst($prayer) . ':', $time);
}

echo "\n";

// Example 4: Specific date
echo "Example 4: Prayer times for London on Ramadan 1st\n";
echo "---------------------------------------------------\n";

$londonCoordinates = new Coordinates(51.5074, -0.1278);
$londonTimezone = new DateTimeZone('Europe/London');
$londonSalahAPI = new SalahAPI($londonCoordinates, null, $londonTimezone);

// Example date: March 1, 2025 (approximation for illustration)
$ramadanDate = new DateTime('2025-03-01', $londonTimezone);
$londonPrayerTimes = $londonSalahAPI->getPrayerTimes($ramadanDate);

echo "Date: " . $ramadanDate->format('l, F j, Y') . "\n";
echo "Location: London (51.5074°N, 0.1278°W)\n";
echo "Method: Muslim World League\n\n";

foreach ($londonPrayerTimes->toArray('g:i A') as $prayer => $time) {
    echo sprintf("%-10s %s\n", ucfirst($prayer) . ':', $time);
}

echo "\n";

// Example 5: Get individual prayer times
echo "Example 5: Accessing individual prayer times\n";
echo "---------------------------------------------\n";

$dhuhr = $prayerTimes->getDhuhr();
$asr = $prayerTimes->getAsr();

echo "Dhuhr prayer time: " . $dhuhr->format('g:i:s A') . "\n";
echo "Asr prayer time: " . $asr->format('g:i:s A') . "\n";

// Calculate time until next prayer
$now = new DateTime('now', $timezone);
$nextPrayer = null;
$prayers = [
    'Fajr' => $prayerTimes->getFajr(),
    'Sunrise' => $prayerTimes->getSunrise(),
    'Dhuhr' => $prayerTimes->getDhuhr(),
    'Asr' => $prayerTimes->getAsr(),
    'Maghrib' => $prayerTimes->getMaghrib(),
    'Isha' => $prayerTimes->getIsha(),
];

foreach ($prayers as $name => $time) {
    if ($time > $now) {
        $nextPrayer = $name;
        $nextPrayerTime = $time;
        break;
    }
}

if ($nextPrayer) {
    $interval = $now->diff($nextPrayerTime);
    echo "\nNext prayer: {$nextPrayer} in {$interval->h} hours and {$interval->i} minutes\n";
} else {
    echo "\nAll prayers for today have passed. Next prayer is tomorrow's Fajr.\n";
}

echo "\n";
echo "===========================================\n";
echo "  Example completed successfully!         \n";
echo "===========================================\n";
