# SalahAPI PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-blue.svg)](https://php.net)

A lightweight PHP library for calculating Islamic prayer times with **zero external runtime dependencies**. Perfect for WordPress plugins, standalone applications, or any PHP project.

## Features

- 🕌 Calculate accurate prayer times for any location
- 🌍 Support for multiple calculation methods (MWL, ISNA, Egypt, Umm al-Qura, etc.)
- 🔧 No external dependencies required (WordPress-friendly)
- ⚡ Lightweight and fast
- 📅 Timezone support
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

```php
<?php

require 'vendor/autoload.php';

use SalahAPI\SalahAPI;
use SalahAPI\Coordinates;
use SalahAPI\CalculationMethod;

// Create coordinates for your location (e.g., New York City)
$coordinates = new Coordinates(40.7128, -74.0060);

// Create timezone
$timezone = new DateTimeZone('America/New_York');

// Initialize SalahAPI
$salahAPI = new SalahAPI($coordinates, null, $timezone);

// Get prayer times for today
$prayerTimes = $salahAPI->getPrayerTimes();

// Display prayer times
echo "Fajr: " . $prayerTimes->getFajr()->format('g:i A') . "\n";
echo "Sunrise: " . $prayerTimes->getSunrise()->format('g:i A') . "\n";
echo "Dhuhr: " . $prayerTimes->getDhuhr()->format('g:i A') . "\n";
echo "Asr: " . $prayerTimes->getAsr()->format('g:i A') . "\n";
echo "Maghrib: " . $prayerTimes->getMaghrib()->format('g:i A') . "\n";
echo "Isha: " . $prayerTimes->getIsha()->format('g:i A') . "\n";
```

## Usage Examples

### Using Different Calculation Methods

```php
use SalahAPI\CalculationMethod;

// Muslim World League (default)
$method = CalculationMethod::fromArray(CalculationMethod::MUSLIM_WORLD_LEAGUE);

// Islamic Society of North America
$method = CalculationMethod::fromArray(CalculationMethod::ISLAMIC_SOCIETY_OF_NORTH_AMERICA);

// Egyptian General Authority
$method = CalculationMethod::fromArray(CalculationMethod::EGYPTIAN_GENERAL_AUTHORITY);

// Umm al-Qura University, Makkah
$method = CalculationMethod::fromArray(CalculationMethod::UMM_AL_QURA);

// Create SalahAPI with custom method
$salahAPI = new SalahAPI($coordinates, $method, $timezone);
```

### Get Prayer Times for a Specific Date

```php
$date = new DateTime('2025-12-25', $timezone);
$prayerTimes = $salahAPI->getPrayerTimes($date);
```

### Get Prayer Times as Array

```php
// Default format (24-hour: H:i)
$times = $prayerTimes->toArray();
// ['fajr' => '05:30', 'sunrise' => '07:00', ...]

// Custom format (12-hour with AM/PM)
$times = $prayerTimes->toArray('g:i A');
// ['fajr' => '5:30 AM', 'sunrise' => '7:00 AM', ...]
```

### WordPress Integration

```php
// In your WordPress plugin or theme
function get_todays_prayer_times() {
    // Get location from WordPress options or settings
    $latitude = get_option('mosque_latitude', 40.7128);
    $longitude = get_option('mosque_longitude', -74.0060);
    $timezone_string = get_option('timezone_string', 'America/New_York');
    
    $coordinates = new \SalahAPI\Coordinates($latitude, $longitude);
    $timezone = new DateTimeZone($timezone_string);
    
    $salahAPI = new \SalahAPI\SalahAPI($coordinates, null, $timezone);
    $prayerTimes = $salahAPI->getPrayerTimes();
    
    return $prayerTimes->toArray('g:i A');
}

// Display in your template
$times = get_todays_prayer_times();
foreach ($times as $prayer => $time) {
    echo ucfirst($prayer) . ": " . $time . "<br>";
}
```

## Available Calculation Methods

| Method | Fajr Angle | Isha Angle |
|--------|-----------|-----------|
| Muslim World League | 18° | 17° |
| Islamic Society of North America (ISNA) | 15° | 15° |
| Egyptian General Authority | 19.5° | 17.5° |
| Umm al-Qura University, Makkah | 18.5° | 90 min |
| University of Islamic Sciences, Karachi | 18° | 18° |
| Institute of Geophysics, Tehran | 17.7° | 14° |
| Moonsighting Committee | 18° | 18° |

## API Reference

### `Coordinates`

Create geographic coordinates:

```php
$coordinates = new Coordinates(float $latitude, float $longitude);
```

### `CalculationMethod`

Create or use predefined calculation methods:

```php
// Use predefined method
$method = CalculationMethod::fromArray(CalculationMethod::MUSLIM_WORLD_LEAGUE);

// Or create custom method
$method = new CalculationMethod(18.0, 17.0); // Fajr angle, Isha angle
```

### `SalahAPI`

Main class for calculating prayer times:

```php
$salahAPI = new SalahAPI(
    Coordinates $coordinates,
    ?CalculationMethod $method = null,  // Defaults to MWL
    ?DateTimeZone $timezone = null      // Defaults to UTC
);

$prayerTimes = $salahAPI->getPrayerTimes(?DateTimeInterface $date = null);
```

### `PrayerTimes`

Result object containing all prayer times:

```php
$prayerTimes->getFajr(): DateTimeInterface
$prayerTimes->getSunrise(): DateTimeInterface
$prayerTimes->getDhuhr(): DateTimeInterface
$prayerTimes->getAsr(): DateTimeInterface
$prayerTimes->getMaghrib(): DateTimeInterface
$prayerTimes->getIsha(): DateTimeInterface
$prayerTimes->toArray(string $format = 'H:i'): array
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

### Project Structure

```
salahapi-php/
├── src/
│   ├── SalahAPI.php          # Main calculator class
│   ├── Coordinates.php        # Geographic coordinates
│   ├── CalculationMethod.php  # Prayer time calculation methods
│   └── PrayerTimes.php        # Result object
├── tests/
│   ├── SalahAPITest.php
│   ├── CoordinatesTest.php
│   ├── CalculationMethodTest.php
│   └── PrayerTimesTest.php
├── scripts/
│   ├── example.php            # Usage example
│   └── test.sh                # Test runner script
├── composer.json
├── phpunit.xml
└── README.md
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
