<?php

/**
 * Example usage of SalahAPI specification classes
 * 
 * This demonstrates how to use the classes defined in the SalahAPI 1.0 specification.
 * Run this script: php scripts/specification-example.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use SalahAPI\SalahAPI;
use SalahAPI\Info;
use SalahAPI\Contact;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;
use SalahAPI\PrayerCalculationOverrideRule;
use SalahAPI\JumuahRule;
use SalahAPI\JumuahLocation;
use SalahAPI\DailyPrayerTimes;
use SalahAPI\CsvUrlParameters;

echo "=======================================================\n";
echo "  SalahAPI Specification 1.0 - PHP Implementation     \n";
echo "=======================================================\n\n";

// Example 1: Create a basic SalahAPI document with CSV data
echo "Example 1: Basic document with CSV data\n";
echo "========================================\n\n";

$contact = new Contact('Support', 'support@example.com');
$info = new Info(
    'New York Islamic Center Prayer Times',
    'Prayer times for New York City using ISNA calculation method',
    '1.0.0',
    $contact
);

$csvUrlParameters = new CsvUrlParameters();
$csvUrlParameters->addDateParameter('fromDate', 'query', 'fromDate', 'YYYY-MM-DD');
$csvUrlParameters->addDateParameter('toDate', 'query', 'toDate', 'YYYY-MM-DD');
$csvUrlParameters->addStaticParameter('apiVersion', 'query', '2.0');

$dailyPrayerTimes = new DailyPrayerTimes(
    'https://example.com/prayer_times',
    'YYYY-MM-DD',
    'HH:mm:ss',
    $csvUrlParameters
);

$document1 = new SalahAPI('1.0', $info, null, null, $dailyPrayerTimes);

echo $document1->toJson();
echo "\n\n";

// Example 2: Create a document with calculation method and Iqama rules
echo "Example 2: Document with calculation method and Iqama rules\n";
echo "============================================================\n\n";

$location = new Location(
    40.7128,
    -74.0060,
    'America/New_York',
    'YYYY-MM-DD',
    'HH:mm:ss',
    'New York',
    'United States'
);

// Create Iqama calculation rules
$fajrRule = new PrayerCalculationRule(
    null,      // static time
    'daily',   // change frequency
    15,        // round minutes
    '04:00',   // earliest
    '06:45',   // latest
    null,      // afterAthanMinutes
    30         // beforeEndMinutes
);

$dhuhrOverrideRule = new PrayerCalculationRule('13:30');
$dhuhrOverride = new PrayerCalculationOverrideRule('daylightSavingsTime', $dhuhrOverrideRule);
$dhuhrRule = new PrayerCalculationRule('12:30', null, null, null, null, null, null, [$dhuhrOverride]);

$asrRule = new PrayerCalculationRule(
    null,      // static time
    'weekly',  // change frequency
    15,        // round minutes
    null,      // earliest
    null,      // latest
    15,        // afterAthanMinutes
    null       // beforeEndMinutes
);

$maghribRule = new PrayerCalculationRule(
    null,      // static time
    'daily',   // change frequency
    1,         // round minutes
    null,      // earliest
    null,      // latest
    10,        // afterAthanMinutes
    null       // beforeEndMinutes
);

$ishaRule = new PrayerCalculationRule(
    null,      // static time
    'weekly',  // change frequency
    1,         // round minutes
    null,      // earliest
    '23:45',   // latest
    10,        // afterAthanMinutes
    null       // beforeEndMinutes
);

$iqamaRules = new IqamaCalculationRules(
    'friday',
    $fajrRule,
    $dhuhrRule,
    $asrRule,
    $maghribRule,
    $ishaRule
);

// Create Jumuah rules
$jumuahLocation = new JumuahLocation(
    'New York Islamic Center',
    '123 Main St, New York, NY 10001'
);

$jumuah1Time = new PrayerCalculationRule('12:00');
$jumuah1 = new JumuahRule('Jumuah 1', $jumuah1Time, $jumuahLocation);

$jumuah2OverrideRule = new PrayerCalculationRule('14:00');
$jumuah2Override = new PrayerCalculationOverrideRule('daylightSavingsTime', $jumuah2OverrideRule);
$jumuah2Time = new PrayerCalculationRule('none', null, null, null, null, null, null, [$jumuah2Override]);
$jumuah2 = new JumuahRule('Jumuah 2', $jumuah2Time, $jumuahLocation);

$calculationMethod = new CalculationMethod(
    'ISNA',
    15.0,
    15.0,
    'Standard',
    'MiddleOfTheNight',
    $iqamaRules,
    [$jumuah1, $jumuah2]
);

$document2 = new SalahAPI('1.0', $info, $location, $calculationMethod);

echo $document2->toJson();
echo "\n\n";

// Example 3: Parse JSON back to object
echo "Example 3: Parse JSON document\n";
echo "===============================\n\n";

$json = '{
    "salahapi": "1.0",
    "info": {
        "title": "Test Islamic Center",
        "description": "Prayer times for test location",
        "version": "1.0.0",
        "contact": {
            "name": "Admin",
            "email": "admin@test.com"
        }
    },
    "location": {
        "latitude": 51.5074,
        "longitude": -0.1278,
        "timezone": "Europe/London",
        "city": "London",
        "country": "United Kingdom",
        "dateFormat": "YYYY-MM-DD",
        "timeFormat": "HH:mm"
    },
    "calculationMethod": {
        "name": "MWL",
        "fajrAngle": 18,
        "ishaAngle": 17,
        "asrCalculationMethod": "Standard",
        "highLatitudeAdjustment": "MiddleOfTheNight"
    }
}';

$parsedDocument = SalahAPI::fromJson($json);

echo "Parsed document:\n";
echo "- Version: " . $parsedDocument->salahapi . "\n";
echo "- Title: " . $parsedDocument->info->title . "\n";
echo "- Location: " . $parsedDocument->location->city . ", " . $parsedDocument->location->country . "\n";
echo "- Calculation Method: " . $parsedDocument->calculationMethod->name . "\n";
echo "- Fajr Angle: " . $parsedDocument->calculationMethod->fajrAngle . "°\n";
echo "- Isha Angle: " . $parsedDocument->calculationMethod->ishaAngle . "°\n";

echo "\n\n=======================================================\n";
echo "  All examples completed successfully!                 \n";
echo "=======================================================\n";
