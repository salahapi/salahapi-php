# SalahAPI PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

A PHP library for working with the SalahAPI specification and calculating Islamic prayer times. This library provides both:
- **SalahAPI Document Structure** - A contract/schema for defining and exchanging prayer times data
- **Prayer Times Builder** - Tools to generate prayer times using various calculation methods

Perfect for WordPress plugins, standalone applications, or any PHP project that needs to manage prayer times.

## Features

- 📋 **SalahAPI Document Contract** - Define prayer times data following the SalahAPI specification v1.0
- 🏗️ **Prayer Times Builder** - Generate accurate prayer times for any location and date range
- 🕌 Support for multiple calculation methods (MWL, ISNA, Egypt, Umm al-Qura, etc.)
- 🔧 Flexible Iqama calculation with daily/weekly frequency support
- 📅 CSV and JSON output formats
- 🌍 Timezone support
- ⚡ Lightweight and fast
- 🧪 Fully tested with PHPUnit

## Installation

Install via Composer:

```bash
composer require salahapi/salahapi-php
```

Or download and include manually (no autoloader needed for simple use).

## Requirements

- PHP 7.4 or higher
- No external libraries required!

## Quick Start

### Using the Builder to Generate Prayer Times

```php
<?php

require 'vendor/autoload.php';

use SalahAPI\Calculations\Builder;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;

// Create location (New York City)
$location = new Location(
    latitude: 40.7128,
    longitude: -74.0060,
    timezone: 'America/New_York'
);

// Create calculation method (Muslim World League)
$calculationMethod = new CalculationMethod(
    name: 'MuslimWorldLeague',
    fajrAngle: 18.0,
    ishaAngle: 17.0,
    highLatitudeAdjustment: 'MiddleOfTheNight'
);

// Initialize the builder
$builder = new Builder($location, $calculationMethod);

// Generate prayer times for a date range
$prayerTimes = $builder->buildAssociative('2025-01-01', '2025-01-31');

// Display the first day
print_r($prayerTimes[0]);
// Output:
// Array
// (
//     [day] => 2025-01-01
//     [fajr_athan] => 05:52
//     [fajr_iqama] => 
//     [sunrise] => 07:20
//     [dhuhr_athan] => 11:59
//     [dhuhr_iqama] => 
//     [asr_athan] => 14:30
//     [asr_iqama] => 
//     [maghrib_athan] => 16:38
//     [maghrib_iqama] => 
//     [isha_athan] => 17:58
//     [isha_iqama] => 
// )
```


## Usage Examples

### 1. Working with the SalahAPI Document Contract

The `SalahAPI` class represents a document structure for defining prayer times data according to the SalahAPI specification. It's useful for storing, exchanging, and validating prayer times information.

#### Creating a SalahAPI Document

```php
use SalahAPI\SalahAPI;
use SalahAPI\Info;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\DailyPrayerTimes;
use SalahAPI\Contact;

// Define metadata about the prayer times
$info = new Info(
    title: 'Islamic Center of New York',
    description: 'Prayer times for the Islamic Center',
    contact: new Contact(
        name: 'Masjid Administrator',
        email: 'admin@example.org'
    ),
    version: '1.0.0'
);

// Define the location
$location = new Location(
    latitude: 40.7128,
    longitude: -74.0060,
    timezone: 'America/New_York',
    locality: 'New York',
    region: 'NY',
    country: 'USA'
);

// Define the calculation method
$calculationMethod = new CalculationMethod(
    name: 'MuslimWorldLeague',
    fajrAngle: 18.0,
    ishaAngle: 17.0
);

// Reference the CSV file with daily prayer times
$dailyPrayerTimes = new DailyPrayerTimes(
    url: 'https://example.org/prayer-times.csv'
);

// Create the SalahAPI document
$salahAPI = new SalahAPI(
    salahapi: '1.0',
    info: $info,
    location: $location,
    calculationMethod: $calculationMethod,
    dailyPrayerTimes: $dailyPrayerTimes
);

// Export as JSON
$json = $salahAPI->toJson();
echo $json;

// Export as array
$array = $salahAPI->toArray();
print_r($array);
```

#### Loading a SalahAPI Document

```php
// From JSON string
$jsonString = file_get_contents('salahapi.json');
$salahAPI = SalahAPI::fromJson($jsonString);

// From array
$data = [
    'salahapi' => '1.0',
    'info' => [
        'title' => 'My Mosque',
        'version' => '1.0.0'
    ],
    'location' => [
        'latitude' => 40.7128,
        'longitude' => -74.0060,
        'timezone' => 'America/New_York'
    ]
];
$salahAPI = SalahAPI::fromArray($data);

// Access properties
echo $salahAPI->info->title; // "My Mosque"
echo $salahAPI->location->timezone; // "America/New_York"
```

### 2. Generating Prayer Times with the Builder

The `Builder` class uses the SalahAPI contract structure to generate actual prayer times for a date range.

#### Athan ONLY Prayer Times Generation

```php
use SalahAPI\Calculations\Builder;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;

$location = new Location(
    latitude: 40.7128,
    longitude: -74.0060,
    timezone: 'America/New_York'
);

$calculationMethod = new CalculationMethod(
    name: 'MuslimWorldLeague',
    fajrAngle: 18.0,
    ishaAngle: 17.0
);

$builder = new Builder($location, $calculationMethod);

// Generate as CSV string
$csv = $builder->buildCsv('2025-01-01', '2025-01-31');
file_put_contents('prayer-times.csv', $csv);

// Generate as associative array
$times = $builder->buildAssociative('2025-01-01', '2025-01-31');
foreach ($times as $day) {
    echo "{$day['day']}: Fajr {$day['fajr_athan']}, Dhuhr {$day['dhuhr_athan']}\n";
}
```

#### Adding Iqama Times with Daily Frequency

```php
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;

// Define Iqama calculation rules
$iqamaRules = new IqamaCalculationRules(
    fajr: new PrayerCalculationRule(afterAthanMinutes: 15),
    dhuhr: new PrayerCalculationRule(afterAthanMinutes: 10),
    asr: new PrayerCalculationRule(afterAthanMinutes: 10),
    maghrib: new PrayerCalculationRule(afterAthanMinutes: 5),
    isha: new PrayerCalculationRule(afterAthanMinutes: 10)
);

$calculationMethod = new CalculationMethod(
    name: 'MuslimWorldLeague',
    fajrAngle: 18.0,
    ishaAngle: 17.0,
    iqamaCalculationRules: $iqamaRules
);

$builder = new Builder($location, $calculationMethod);
$times = $builder->buildAssociative('2025-01-01', '2025-01-31');

// Now includes iqama times
echo $times[0]['fajr_iqama']; // e.g., "06:07" (15 minutes after athan)
```

#### Adding Iqama Times with Weekly Frequency

```php
// Change Iqama times weekly (every Friday)
$iqamaRules = new IqamaCalculationRules(
    fajr: new PrayerCalculationRule(afterAthanMinutes: 20),
    dhuhr: new PrayerCalculationRule(afterAthanMinutes: 15),
    asr: new PrayerCalculationRule(afterAthanMinutes: 15),
    maghrib: new PrayerCalculationRule(afterAthanMinutes: 5),
    isha: new PrayerCalculationRule(afterAthanMinutes: 15),
    changeOn: 'Friday'  // Recalculate Iqama times weekly
);

$calculationMethod = new CalculationMethod(
    name: 'MuslimWorldLeague',
    fajrAngle: 18.0,
    ishaAngle: 17.0,
    iqamaCalculationRules: $iqamaRules
);

$builder = new Builder($location, $calculationMethod);
$times = $builder->buildAssociative('2025-01-01', '2025-01-31');

// Iqama times will be calculated once per week and applied to all days in that week
```

#### Using Fixed Iqama Times

```php
// Set fixed time for Iqama (e.g., Maghrib always at 5 minutes after sunset)
$iqamaRules = new IqamaCalculationRules(
    fajr: new PrayerCalculationRule(afterAthanMinutes: 15),
    dhuhr: new PrayerCalculationRule(fixedTime: '13:30'),  // Always at 1:30 PM
    asr: new PrayerCalculationRule(afterAthanMinutes: 10),
    maghrib: new PrayerCalculationRule(afterAthanMinutes: 5),
    isha: new PrayerCalculationRule(fixedTime: '20:00')  // Always at 8:00 PM
);
```

#### Using beforeEndMinutes for Fajr

```php
// Fajr Iqama: 10 minutes before sunrise
$iqamaRules = new IqamaCalculationRules(
    fajr: new PrayerCalculationRule(beforeEndMinutes: 10),
    // ... other prayers
);
```

### 3. Complete Example: Generate and Save SalahAPI Document

```php
use SalahAPI\SalahAPI;
use SalahAPI\Info;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\DailyPrayerTimes;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;
use SalahAPI\CsvUrlParameters;
use SalahAPI\Contact;
use SalahAPI\Calculations\Builder;

// 1. Define the contract
$location = new Location(
    latitude: 40.7128,
    longitude: -74.0060,
    timezone: 'America/New_York',
    locality: 'New York',
    region: 'NY',
    country: 'USA'
);

$iqamaRules = new IqamaCalculationRules(
    fajr: new PrayerCalculationRule(afterAthanMinutes: 15),
    dhuhr: new PrayerCalculationRule(afterAthanMinutes: 10),
    asr: new PrayerCalculationRule(afterAthanMinutes: 10),
    maghrib: new PrayerCalculationRule(afterAthanMinutes: 5),
    isha: new PrayerCalculationRule(afterAthanMinutes: 10),
    changeOn: 'Friday'
);

$calculationMethod = new CalculationMethod(
    name: 'MuslimWorldLeague',
    fajrAngle: 18.0,
    ishaAngle: 17.0,
    iqamaCalculationRules: $iqamaRules
);

// 2. Generate prayer times using the Builder
$builder = new Builder($location, $calculationMethod);
$csv = $builder->buildCsv('2025-01-01', '2025-12-31');
file_put_contents('public/prayer-times-2025.csv', $csv);

// 3. Create the SalahAPI document
$salahAPI = new SalahAPI(
    salahapi: '1.0',
    info: new Info(
        title: 'Islamic Center of New York - 2025',
        description: 'Prayer times for the year 2025',
        contact: new Contact(
            name: 'Masjid Administrator',
            email: 'admin@icny.org',
            url: 'https://icny.org'
        ),
        version: '2025.1.0'
    ),
    location: $location,
    calculationMethod: $calculationMethod,
    dailyPrayerTimes: new DailyPrayerTimes(
        url: 'https://icny.org/prayer-times-2025.csv',
        parameters: new CsvUrlParameters(
            year: 2025,
            startDate: '2025-01-01',
            endDate: '2025-12-31'
        )
    )
);

// 4. Save the document
file_put_contents('public/salahapi.json', $salahAPI->toJson());

echo "Generated prayer times and SalahAPI document!\n";
echo "- CSV: public/prayer-times-2025.csv\n";
echo "- JSON: public/salahapi.json\n";
```


## Available Calculation Methods

The Builder supports multiple calculation methods for prayer times:

| Method Name | Fajr Angle | Isha Angle | Description |
|------------|-----------|-----------|-------------|
| MuslimWorldLeague | 18° | 17° | Muslim World League |
| IslamicSocietyOfNorthAmerica | 15° | 15° | ISNA |
| EgyptianGeneralAuthorityOfSurvey | 19.5° | 17.5° | Egyptian General Authority |
| UmmAlQuraUniversityMakkah | 18.5° | 90 min | Umm al-Qura |
| UniversityOfIslamicSciencesKarachi | 18° | 18° | Karachi |
| InstituteOfGeophysicsUniversityOfTehran | 17.7° | 14° | Tehran |
| MoonsightingCommittee | 18° | 18° | Moonsighting Committee |

Example:
```php
$method = new CalculationMethod(
    name: 'IslamicSocietyOfNorthAmerica',
    fajrAngle: 15.0,
    ishaAngle: 15.0
);
```

## API Reference

### Core Classes

#### `SalahAPI`
Main document contract class representing the SalahAPI specification.

```php
$salahAPI = new SalahAPI(
    string $salahapi = '1.0',           // Specification version
    ?Info $info = null,                 // Metadata
    ?Location $location = null,         // Geographic location
    ?CalculationMethod $calculationMethod = null,  // Calculation parameters
    ?DailyPrayerTimes $dailyPrayerTimes = null    // CSV reference
);

// Methods
$salahAPI->toArray(): array              // Convert to array
$salahAPI->toJson(int $options): string  // Convert to JSON
SalahAPI::fromArray(array $data): self   // Create from array
SalahAPI::fromJson(string $json): self   // Create from JSON
```

#### `Builder`
Generates prayer times using the SalahAPI contract.

```php
$builder = new Builder(
    Location $location,
    CalculationMethod $calculationMethod,
    int $elevation = 0  // Elevation in meters
);

// Methods
$builder->build($startDate, $endDate): array  // Returns 2D array with header
$builder->buildCsv($startDate, $endDate): string  // Returns CSV string
$builder->buildAssociative($startDate, $endDate): array  // Returns associative arrays
```

#### `Location`
Geographic coordinates and timezone information.

```php
$location = new Location(
    float $latitude,
    float $longitude,
    string $timezone,
    ?string $locality = null,
    ?string $region = null,
    ?string $country = null,
    ?string $countryCode = null
);

// Methods
$location->toArray(): array
Location::fromArray(array $data): self
```

#### `CalculationMethod`
Prayer time calculation parameters.

```php
$method = new CalculationMethod(
    string $name,                      // Method name
    float $fajrAngle,                  // Fajr angle
    float $ishaAngle,                  // Isha angle
    ?string $asrCalculationMethod = null,  // 'Standard' or 'Hanafi'
    ?string $highLatitudeAdjustment = null,  // Adjustment method
    ?IqamaCalculationRules $iqamaCalculationRules = null
);

// Methods
$method->toArray(): array
CalculationMethod::fromArray(array $data): self
```

#### `IqamaCalculationRules`
Rules for calculating Iqama (congregation) times.

```php
$rules = new IqamaCalculationRules(
    ?PrayerCalculationRule $fajr = null,
    ?PrayerCalculationRule $dhuhr = null,
    ?PrayerCalculationRule $asr = null,
    ?PrayerCalculationRule $maghrib = null,
    ?PrayerCalculationRule $isha = null,
    ?string $changeOn = null  // 'Friday', 'Monday', etc. for weekly frequency
);
```

#### `PrayerCalculationRule`
Individual prayer Iqama calculation rule.

```php
$rule = new PrayerCalculationRule(
    ?int $afterAthanMinutes = null,     // Minutes after athan
    ?string $fixedTime = null,           // Fixed time (HH:MM)
    ?int $beforeEndMinutes = null        // Minutes before next prayer (Fajr only)
);
```

#### `Info`
Metadata about the prayer times data.

```php
$info = new Info(
    string $title,
    ?string $description = null,
    ?Contact $contact = null,
    ?string $termsOfService = null,
    ?string $license = null,
    ?string $version = null
);
```

#### `DailyPrayerTimes`
Reference to CSV file with daily prayer times.

```php
$dailyPrayerTimes = new DailyPrayerTimes(
    string $url,
    ?CsvUrlParameters $parameters = null
);
```

## Development

### Install Dependencies

```bash
composer install
```

### Run Tests

```bash
# Run all tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test suite
composer test-unit
```


### Command Line Tool

The library includes a convenient shell script for calculating prayer times from the command line:

```bash
# Calculate prayer times for a date range
./scripts/calculate.sh 2024-01-01 2024-01-31 config.json

# Output is in CSV format
day,fajr_athan,fajr_iqama,sunrise,dhuhr_athan,dhuhr_iqama,asr_athan,asr_iqama,maghrib_athan,maghrib_iqama,isha_athan,isha_iqama
2024-01-01,05:58,06:13,07:20,11:59,13:15,14:21,14:41,16:40,16:45,18:01,20:00
2024-01-02,05:58,06:13,07:20,12:00,13:15,14:22,14:42,16:41,16:46,18:02,20:00
...

# Redirect to a file
./scripts/calculate.sh 2024-01-01 2024-12-31 config.json > prayer-times.csv

# Use in pipelines
./scripts/calculate.sh 2024-01-01 2024-01-31 config.json | head -10
```

**JSON Configuration File Format:**

Create a JSON file with your SalahAPI configuration:

```json
{
    "salahapi": "1.0",
    "location": {
        "latitude": 40.7128,
        "longitude": -74.0060,
        "timezone": "America/New_York",
        "city": "New York",
        "country": "United States"
    },
    "calculationMethod": {
        "name": "ISNA",
        "fajrAngle": 15.0,
        "ishaAngle": 15.0,
        "asrCalculationMethod": "Standard",
        "highLatitudeAdjustment": "MiddleOfTheNight",
        "iqamaCalculationRules": {
            "changeOn": "Friday",
            "fajr": {
                "change": "daily",
                "afterAthanMinutes": 15
            },
            "dhuhr": {
                "static": "13:15"
            },
            "asr": {
                "change": "daily",
                "afterAthanMinutes": 20
            },
            "maghrib": {
                "change": "daily",
                "afterAthanMinutes": 5
            },
            "isha": {
                "static": "20:00"
            }
        }
    }
}
```

See [example-config.json](example-config.json) and [example-config-iqama.json](example-config-iqama.json) for complete examples.

### Project Structure

```
salahapi-php/
├── src/
│   ├── SalahAPI.php                    # Main document contract class
│   ├── Info.php                        # Metadata
│   ├── Location.php                    # Geographic location
│   ├── CalculationMethod.php           # Calculation parameters
│   ├── DailyPrayerTimes.php           # CSV reference
│   ├── IqamaCalculationRules.php      # Iqama rules
│   ├── PrayerCalculationRule.php      # Individual prayer rule
│   ├── Contact.php                     # Contact information
│   ├── CsvUrlParameters.php           # CSV URL parameters
│   └── Calculations/
│       ├── Builder.php                 # Prayer times generator
│       ├── PrayerTimes.php            # Core calculation engine
│       ├── IqamaCalculator.php        # Iqama calculation logic
│       ├── Method.php                  # Calculation method interface
│       └── TimeHelpers.php            # Time utility functions
├── tests/
│   ├── SalahAPITest.php
│   └── Calculations/
│       ├── BuilderTest.php
│       ├── PrayerTimesTest.php
│       ├── IqamaCalculatorTest.php
│       └── TimeHelpersTest.php
├── scripts/
│   ├── calculate.sh                    # Calculate prayer times from command line
│   ├── check.sh                        # Code quality checks
│   ├── install.sh                      # Installation script
│   └── test.sh                         # Test runner
├── example-config.json                 # Example configuration
├── example-config-iqama.json          # Example with iqama rules
├── composer.json
├── phpunit.xml
└── README.md
```

## WordPress Integration

Here's how to use the library in a WordPress plugin:

```php
<?php
/**
 * Plugin Name: Mosque Prayer Times
 * Description: Display prayer times using SalahAPI
 */

use SalahAPI\Calculations\Builder;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;

// Hook into WordPress
add_action('init', 'generate_monthly_prayer_times');
add_shortcode('prayer_times', 'display_prayer_times_shortcode');

function generate_monthly_prayer_times() {
    // Only regenerate on the first day of the month
    if (date('d') !== '01') {
        return;
    }
    
    // Get settings from WordPress options
    $latitude = get_option('mosque_latitude', 40.7128);
    $longitude = get_option('mosque_longitude', -74.0060);
    $timezone = get_option('timezone_string', 'America/New_York');
    
    $location = new Location($latitude, $longitude, $timezone);
    
    $iqamaRules = new IqamaCalculationRules(
        fajr: new PrayerCalculationRule(afterAthanMinutes: 15),
        dhuhr: new PrayerCalculationRule(afterAthanMinutes: 10),
        asr: new PrayerCalculationRule(afterAthanMinutes: 10),
        maghrib: new PrayerCalculationRule(afterAthanMinutes: 5),
        isha: new PrayerCalculationRule(afterAthanMinutes: 10),
        changeOn: 'Friday'
    );
    
    $method = new CalculationMethod(
        name: 'IslamicSocietyOfNorthAmerica',
        fajrAngle: 15.0,
        ishaAngle: 15.0,
        iqamaCalculationRules: $iqamaRules
    );
    
    $builder = new Builder($location, $method);
    
    // Generate for current month
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
    
    $prayerTimes = $builder->buildAssociative($startDate, $endDate);
    
    // Store in WordPress options
    update_option('prayer_times_data', $prayerTimes);
}

function display_prayer_times_shortcode($atts) {
    $times = get_option('prayer_times_data', []);
    $today = date('Y-m-d');
    
    // Find today's times
    $todayTimes = null;
    foreach ($times as $day) {
        if ($day['day'] === $today) {
            $todayTimes = $day;
            break;
        }
    }
    
    if (!$todayTimes) {
        return '<p>Prayer times not available.</p>';
    }
    
    // Build HTML
    ob_start();
    ?>
    <div class="prayer-times">
        <h3>Today's Prayer Times</h3>
        <table>
            <tr>
                <th>Prayer</th>
                <th>Athan</th>
                <th>Iqama</th>
            </tr>
            <tr>
                <td>Fajr</td>
                <td><?php echo $todayTimes['fajr_athan']; ?></td>
                <td><?php echo $todayTimes['fajr_iqama']; ?></td>
            </tr>
            <tr>
                <td>Dhuhr</td>
                <td><?php echo $todayTimes['dhuhr_athan']; ?></td>
                <td><?php echo $todayTimes['dhuhr_iqama']; ?></td>
            </tr>
            <tr>
                <td>Asr</td>
                <td><?php echo $todayTimes['asr_athan']; ?></td>
                <td><?php echo $todayTimes['asr_iqama']; ?></td>
            </tr>
            <tr>
                <td>Maghrib</td>
                <td><?php echo $todayTimes['maghrib_athan']; ?></td>
                <td><?php echo $todayTimes['maghrib_iqama']; ?></td>
            </tr>
            <tr>
                <td>Isha</td>
                <td><?php echo $todayTimes['isha_athan']; ?></td>
                <td><?php echo $todayTimes['isha_iqama']; ?></td>
            </tr>
        </table>
        <p>Sunrise: <?php echo $todayTimes['sunrise']; ?></p>
    </div>
    <?php
    return ob_get_clean();
}
```

Use the shortcode in any post or page:
```
[prayer_times]
```

## Common Locations

Here are coordinates for some major cities:

```php
// Mecca, Saudi Arabia
new Coordinates(21.4225, 39.8262);

// Medina, Saudi Arabia
new Coordinates(24.5247, 39.5692);

// Istanbul, Turkey
new Coordinates(41.0082, 28.9784);

// London, UK
new Coordinates(51.5074, -0.1278);

// New York, USA
new Coordinates(40.7128, -74.0060);

// Dubai, UAE
new Coordinates(25.2048, 55.2708);
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Testing

This library includes comprehensive unit tests. Run them with:

```bash
composer test
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Credits

Built with ❤️ for the Muslim community.

## Support

For issues, questions, or contributions, please visit:
- GitHub: [https://github.com/salahapi/salahapi-php](https://github.com/salahapi/salahapi-php)
- Issues: [https://github.com/salahapi/salahapi-php/issues](https://github.com/salahapi/salahapi-php/issues)
