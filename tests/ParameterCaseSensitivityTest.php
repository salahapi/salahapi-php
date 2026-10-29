<?php

namespace SalahAPI\Tests\Calculations;

use PHPUnit\Framework\TestCase;
use SalahAPI\Calculations\Builder;
use SalahAPI\Calculations\IqamaCalculator;
use SalahAPI\Calculations\Method;
use SalahAPI\Calculations\PrayerTimes;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\Location;
use SalahAPI\PrayerCalculationOverrideRule;
use SalahAPI\PrayerCalculationRule;
use DateTime;
use DateTimeZone;

/**
 * Parameter Case Sensitivity Tests
 *
 * All string parameters (rule change frequency, override conditions, changeOn
 * weekday, calculation method name, Asr school, high-latitude adjustment,
 * midnight mode, time format, timezone) must be matched case-insensitively,
 * since configuration is served by an API that may use different casing
 * (e.g. "isna", "friday", "middleOfTheNight").
 *
 * @package SalahAPI\Tests\Calculations
 */
class ParameterCaseSensitivityTest extends TestCase
{
    /**
     * Two January days (no DST) with known Athan times
     */
    private function januaryDaysData(): array
    {
        return [
            [
                'date' => new DateTime('2023-01-01 00:00:00'),
                'athan' => [
                    'fajr' => new DateTime('2023-01-01 05:30:00'),
                    'sunrise' => new DateTime('2023-01-01 07:30:00'),
                ],
            ],
            [
                'date' => new DateTime('2023-01-02 00:00:00'),
                'athan' => [
                    'fajr' => new DateTime('2023-01-02 05:31:00'),
                    'sunrise' => new DateTime('2023-01-02 07:31:00'),
                ],
            ],
        ];
    }

    /**
     * Days around the fall DST change with Dhuhr Athan times from the
     * production CSV (Oct 31 is DST, Nov 1 Athan is after the fall-back)
     */
    private function fallChangeDhuhrDaysData(): array
    {
        $tz = new DateTimeZone('America/Los_Angeles');

        return [
            [
                'date' => new DateTime('2026-10-31 00:00:00', $tz),
                'athan' => ['dhuhr' => new DateTime('2026-10-31 12:52:00', $tz)],
            ],
            [
                'date' => new DateTime('2026-11-01 00:00:00', $tz),
                'athan' => ['dhuhr' => new DateTime('2026-11-01 11:52:00', $tz)],
            ],
            [
                'date' => new DateTime('2026-11-02 00:00:00', $tz),
                'athan' => ['dhuhr' => new DateTime('2026-11-02 11:52:00', $tz)],
            ],
        ];
    }

    /**
     * Calculation method with identical simple rules for all prayers
     */
    private function calculationMethod(
        ?string $changeOn,
        string $change,
        string $highLatitudeAdjustment = 'MiddleOfTheNight',
        string $methodName = 'ISNA'
    ): CalculationMethod {
        return new CalculationMethod(
            $methodName,
            15.0,
            15.0,
            'Standard',
            $highLatitudeAdjustment,
            new IqamaCalculationRules(
                $changeOn,
                new PrayerCalculationRule(null, $change, 5, null, null, 20, null, null),
                new PrayerCalculationRule(null, $change, 5, null, null, 25, null, null),
                new PrayerCalculationRule(null, $change, 5, null, null, 10, null, null),
                new PrayerCalculationRule(null, $change, 5, null, null, 5, null, null),
                new PrayerCalculationRule(null, $change, 5, null, null, 15, null, null)
            )
        );
    }

    private function newYorkLocation(): Location
    {
        return new Location(40.7128, -74.0060, 'America/New_York', 'Y-m-d', 'H:i');
    }

    private function seattleLocation(): Location
    {
        return new Location(47.6062, -122.3321, 'America/Los_Angeles', 'Y-m-d', 'H:i');
    }

    private function buildCsv(Location $location, CalculationMethod $method, string $start, string $end): array
    {
        return (new Builder($location, $method))->build($start, $end);
    }

    /**
     * @dataProvider weeklyChangeCasingProvider
     */
    public function testWeeklyChangeParameterIsCaseInsensitive(string $change)
    {
        $rule = new PrayerCalculationRule(null, $change, 5, null, null, 20, null, null);

        $results = IqamaCalculator::calculateIqama($this->januaryDaysData(), 'fajr', $rule);

        // Weekly semantics: one value for both days (latest candidate 05:31 -> 05:35 + 20)
        $this->assertSame('05:55:00', $results[0]->format('H:i:s'));
        $this->assertSame('05:55:00', $results[1]->format('H:i:s'));
    }

    public static function weeklyChangeCasingProvider(): array
    {
        return [
            'lowercase' => ['weekly'],
            'capitalized' => ['Weekly'],
            'uppercase' => ['WEEKLY'],
            'mixed' => ['wEeKlY'],
        ];
    }

    /**
     * @dataProvider dailyChangeCasingProvider
     */
    public function testDailyChangeParameterIsCaseInsensitive(string $change)
    {
        $rule = new PrayerCalculationRule(null, $change, 5, null, null, 20, null, null);

        $results = IqamaCalculator::calculateIqama($this->januaryDaysData(), 'fajr', $rule);

        // Daily semantics: per-day values (05:30 + 20 = 05:50, 05:31 -> 05:35 + 20 = 05:55)
        $this->assertSame('05:50:00', $results[0]->format('H:i:s'));
        $this->assertSame('05:55:00', $results[1]->format('H:i:s'));
    }

    public static function dailyChangeCasingProvider(): array
    {
        return [
            'lowercase' => ['daily'],
            'capitalized' => ['Daily'],
            'uppercase' => ['DAILY'],
            'mixed' => ['dAiLy'],
        ];
    }

    /**
     * @dataProvider daylightSavingsTimeConditionCasingProvider
     */
    public function testDaylightSavingsTimeOverrideConditionIsCaseInsensitive(string $condition)
    {
        $baseRule = new PrayerCalculationRule('12:30', null, null, null, null, null, null, [
            new PrayerCalculationOverrideRule($condition, new PrayerCalculationRule('13:30')),
        ]);

        $results = IqamaCalculator::calculateIqama($this->fallChangeDhuhrDaysData(), 'dhuhr', $baseRule);

        $this->assertSame('13:30:00', $results[0]->format('H:i:s'), 'Oct 31 (DST) should use the override');
        $this->assertSame('12:30:00', $results[1]->format('H:i:s'), 'Nov 1 Athan is after the fall-back, should use standard');
        $this->assertSame('12:30:00', $results[2]->format('H:i:s'), 'Nov 2 (standard) should use standard');
    }

    public static function daylightSavingsTimeConditionCasingProvider(): array
    {
        return [
            'camelCase' => ['daylightSavingsTime'],
            'capitalized words' => ['DaylightSavingstime'],
            'uppercase' => ['DAYLIGHTSAVINGSTIME'],
            'lowercase' => ['daylightsavingstime'],
            'mixed' => ['dAyLiGhTsAvInGsTiMe'],
        ];
    }

    /**
     * @dataProvider ramadanConditionCasingProvider
     */
    public function testRamadanOverrideConditionIsCaseInsensitive(string $condition)
    {
        // Feb 25, 2026 falls within Ramadan 1447
        $tz = new DateTimeZone('America/Los_Angeles');
        $daysData = [
            [
                'date' => new DateTime('2026-02-25 00:00:00', $tz),
                'athan' => [
                    'fajr' => new DateTime('2026-02-25 05:32:00', $tz),
                    'sunrise' => new DateTime('2026-02-25 06:57:00', $tz),
                ],
            ],
        ];

        $baseRule = new PrayerCalculationRule(null, 'weekly', 15, '04:30', '06:45', null, 30, [
            new PrayerCalculationOverrideRule(
                $condition,
                new PrayerCalculationRule(null, 'daily', 1, null, null, 15, null, null)
            ),
        ]);

        $results = IqamaCalculator::calculateIqama($daysData, 'fajr', $baseRule, 'sunrise');

        // The Ramadan override applies (daily, Athan + 15): 05:32 + 15 = 05:47
        $this->assertSame('05:47:00', $results[0]->format('H:i:s'), "Condition '{$condition}' should match Ramadan");
    }

    public static function ramadanConditionCasingProvider(): array
    {
        return [
            'lowercase' => ['ramadan'],
            'capitalized' => ['Ramadan'],
            'uppercase' => ['RAMADAN'],
            'mixed' => ['rAmAdAn'],
        ];
    }

    public function testChangeOnParameterIsCaseInsensitive()
    {
        $canonicalFriday = $this->buildCsv(
            $this->newYorkLocation(),
            $this->calculationMethod('Friday', 'weekly'),
            '2023-01-07',
            '2023-01-12'
        );

        foreach (['friday', 'FRIDAY', 'FriDay'] as $changeOn) {
            $this->assertSame(
                $canonicalFriday,
                $this->buildCsv($this->newYorkLocation(), $this->calculationMethod($changeOn, 'weekly'), '2023-01-07', '2023-01-12'),
                "changeOn '{$changeOn}' must batch weeks like 'Friday'"
            );
        }

        // A lowercase weekday must not silently fall back to the Friday default
        $canonicalSunday = $this->buildCsv(
            $this->newYorkLocation(),
            $this->calculationMethod('Sunday', 'weekly'),
            '2023-01-07',
            '2023-01-12'
        );

        $this->assertSame(
            $canonicalSunday,
            $this->buildCsv($this->newYorkLocation(), $this->calculationMethod('sunday', 'weekly'), '2023-01-07', '2023-01-12'),
            "changeOn 'sunday' must batch weeks like 'Sunday'"
        );

        $this->assertNotEquals($canonicalFriday, $canonicalSunday, 'Friday and Sunday batching must differ for this range');
    }

    public function testHighLatitudeAdjustmentParameterIsCaseInsensitive()
    {
        // Seattle in June: middleOfTheNight and AngleBased produce different times
        $canonicalMotn = $this->buildCsv(
            $this->seattleLocation(),
            $this->calculationMethod(null, 'daily', 'MiddleOfTheNight'),
            '2026-06-01',
            '2026-06-30'
        );

        foreach (['middleOfTheNight', 'MIDDLEOFTHENIGHT', 'middle_of_the_night', 'NIGHTMIDDLE', 'MIDDLE_OF_THE_NIGHT'] as $adjustment) {
            $this->assertSame(
                $canonicalMotn,
                $this->buildCsv($this->seattleLocation(), $this->calculationMethod(null, 'daily', $adjustment), '2026-06-01', '2026-06-30'),
                "highLatitudeAdjustment '{$adjustment}' must behave like 'MiddleOfTheNight'"
            );
        }

        $canonicalAngle = $this->buildCsv(
            $this->seattleLocation(),
            $this->calculationMethod(null, 'daily', 'AngleBased'),
            '2026-06-01',
            '2026-06-30'
        );

        foreach (['ANGLE_BASED', 'anglebased', 'angle_based'] as $adjustment) {
            $this->assertSame(
                $canonicalAngle,
                $this->buildCsv($this->seattleLocation(), $this->calculationMethod(null, 'daily', $adjustment), '2026-06-01', '2026-06-30'),
                "highLatitudeAdjustment '{$adjustment}' must behave like 'AngleBased'"
            );
        }

        $this->assertNotEquals($canonicalMotn, $canonicalAngle, 'The adjustment families must differ for this range');
    }

    public function testCalculationMethodNameIsCaseInsensitive()
    {
        $date = new DateTime('2026-01-15 00:00:00', new DateTimeZone('America/Los_Angeles'));
        $getTimes = function (string $name) use ($date): array {
            return (new PrayerTimes($name))->getTimes(
                $date,
                47.777856,
                -122.190302,
                0,
                PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
                null,
                PrayerTimes::TIME_FORMAT_24H
            );
        };

        $canonical = $getTimes(Method::METHOD_ISNA);

        foreach (['isna', 'Isna', 'iSnA'] as $name) {
            $this->assertSame($canonical, $getTimes($name), "Method name '{$name}' must behave like 'ISNA'");
        }

        $this->assertSame(
            Method::METHOD_ISNA,
            (new PrayerTimes('isna'))->getMethod(),
            "Method name casing must be normalized to the canonical constant"
        );

        $this->assertNotEquals($getTimes(Method::METHOD_MWL), $canonical, 'ISNA and MWL must produce different times for this date');
    }

    public function testAsrSchoolParameterIsCaseInsensitive()
    {
        $date = new DateTime('2026-01-15 00:00:00', new DateTimeZone('America/Los_Angeles'));
        $getTimes = function (string $school) use ($date): array {
            return (new PrayerTimes(Method::METHOD_ISNA, $school))->getTimes(
                $date,
                47.777856,
                -122.190302,
                0,
                PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
                null,
                PrayerTimes::TIME_FORMAT_24H
            );
        };

        $canonicalHanafi = $getTimes(PrayerTimes::SCHOOL_HANAFI);

        foreach (['hanafi', 'Hanafi', 'HanaFi'] as $school) {
            $this->assertSame($canonicalHanafi, $getTimes($school), "Asr school '{$school}' must behave like 'HANAFI'");
        }

        $canonicalStandard = $getTimes(PrayerTimes::SCHOOL_STANDARD);

        foreach (['standard', 'Standard', 'STANDARD'] as $school) {
            $this->assertSame($canonicalStandard, $getTimes($school), "Asr school '{$school}' must behave like 'STANDARD'");
        }

        $this->assertNotEquals($canonicalHanafi, $canonicalStandard, 'The Asr schools must produce different times for this date');
    }

    public function testHighLatitudeAdjustmentMethodParameterIsCaseInsensitive()
    {
        // June 15 in Seattle: MOTN and ANGLE_BASED produce different times
        $date = new DateTime('2026-06-15 00:00:00', new DateTimeZone('America/Los_Angeles'));
        $getTimes = function (string $adjustment) use ($date): array {
            return (new PrayerTimes(Method::METHOD_ISNA))->getTimes(
                $date,
                47.6062,
                -122.3321,
                0,
                $adjustment,
                null,
                PrayerTimes::TIME_FORMAT_24H
            );
        };

        $canonicalMotn = $getTimes(PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN);

        foreach (['middle_of_the_night', 'middleOfTheNight', 'MIDDLE_OF_THE_NIGHT', 'NightMiddle'] as $adjustment) {
            $this->assertSame($canonicalMotn, $getTimes($adjustment), "Adjustment '{$adjustment}' must behave like 'MIDDLE_OF_THE_NIGHT'");
        }

        $canonicalAngle = $getTimes(PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_ANGLE);

        foreach (['angle_based', 'angleBased', 'AngleBased'] as $adjustment) {
            $this->assertSame($canonicalAngle, $getTimes($adjustment), "Adjustment '{$adjustment}' must behave like 'ANGLE_BASED'");
        }

        $this->assertNotEquals($canonicalMotn, $canonicalAngle, 'The adjustment families must differ for this date');
    }

    public function testMidnightModeParameterIsCaseInsensitive()
    {
        $date = new DateTime('2026-01-15 00:00:00', new DateTimeZone('America/Los_Angeles'));
        $getTimes = function (string $mode) use ($date): array {
            return (new PrayerTimes(Method::METHOD_ISNA))->getTimes(
                $date,
                47.777856,
                -122.190302,
                0,
                PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
                $mode,
                PrayerTimes::TIME_FORMAT_24H
            );
        };

        $canonicalJafari = $getTimes(PrayerTimes::MIDNIGHT_MODE_JAFARI);

        foreach (['jafari', 'Jafari'] as $mode) {
            $this->assertSame($canonicalJafari, $getTimes($mode), "Midnight mode '{$mode}' must behave like 'JAFARI'");
        }

        $this->assertNotEquals($getTimes(PrayerTimes::MIDNIGHT_MODE_STANDARD), $canonicalJafari, 'The midnight modes must differ for this date');
    }

    public function testTimeFormatParameterIsCaseInsensitive()
    {
        $date = new DateTime('2026-01-15 00:00:00', new DateTimeZone('America/Los_Angeles'));
        $getTimes = function (string $format) use ($date): array {
            return (new PrayerTimes(Method::METHOD_ISNA))->getTimes(
                $date,
                47.777856,
                -122.190302,
                0,
                PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
                null,
                $format
            );
        };

        $this->assertSame($getTimes('24h'), $getTimes('24H'), "Format '24H' must behave like '24h'");
        $this->assertSame($getTimes('12hNS'), $getTimes('12hNs'), "Format '12hNs' must behave like '12hNS'");
        $this->assertNotEquals($getTimes('24h'), $getTimes('12h'), 'The formats must produce different output');
    }

    public function testLocationTimezoneParameterIsCaseInsensitive()
    {
        $canonical = new DateTimeZone('America/Los_Angeles');

        foreach (['america/los_angeles', 'AMERICA/LOS_ANGELES', 'America/los_angeles'] as $timezone) {
            $variant = new DateTimeZone($timezone);

            // One instant in DST and one in standard time
            $dst = new DateTime('2026-10-31 12:52:00', $canonical);
            $std = new DateTime('2026-11-01 11:52:00', $canonical);

            $this->assertSame($dst->getTimestamp(), (new DateTime('2026-10-31 12:52:00', $variant))->getTimestamp(), "Timezone '{$timezone}' must resolve DST instants like the canonical casing");
            $this->assertSame($std->getTimestamp(), (new DateTime('2026-11-01 11:52:00', $variant))->getTimestamp(), "Timezone '{$timezone}' must resolve standard-time instants like the canonical casing");
            $this->assertSame($dst->format('I'), (new DateTime('2026-10-31 12:52:00', $variant))->format('I'));
            $this->assertSame($std->format('I'), (new DateTime('2026-11-01 11:52:00', $variant))->format('I'));
        }
    }

    /**
     * End-to-end: a configuration assembled with API-style casing (the live
     * endpoint serves "isna", "friday", "middleOfTheNight", "standard", "hanafi")
     * plus some aggressive casing variants must produce exactly the same output
     * as the same configuration in canonical casing.
     */
    public function testApiCasedConfigurationMatchesCanonicalCasing()
    {
        $apiCased = [
            'name' => 'isna',
            'fajrAngle' => 15.0,
            'ishaAngle' => 15.0,
            'asrCalculationMethod' => 'standard',
            'asrIqamaCalculationMethod' => 'hanafi',
            'highLatitudeAdjustment' => 'middleOfTheNight',
            'iqamaCalculationRules' => [
                'changeOn' => 'friday',
                'fajr' => ['change' => 'WEEKLY', 'roundMinutes' => '15', 'earliest' => '04:30', 'latest' => '06:45', 'beforeEndMinutes' => '30'],
                'dhuhr' => ['static' => '12:30', 'overrides' => [['condition' => 'DAYLIGHTSAVINGSTIME', 'time' => ['static' => '13:30']]]],
                'asr' => ['change' => 'weekly', 'roundMinutes' => '15', 'afterAthanMinutes' => '0'],
                'maghrib' => ['change' => 'DAILY', 'roundMinutes' => '1', 'afterAthanMinutes' => '10'],
                'isha' => ['change' => 'daily', 'roundMinutes' => '1', 'earliest' => '19:45', 'latest' => '23:30', 'afterAthanMinutes' => '10'],
            ],
        ];

        $canonical = [
            'name' => 'ISNA',
            'fajrAngle' => 15.0,
            'ishaAngle' => 15.0,
            'asrCalculationMethod' => 'standard',
            'asrIqamaCalculationMethod' => 'hanafi',
            'highLatitudeAdjustment' => 'middleOfTheNight',
            'iqamaCalculationRules' => [
                'changeOn' => 'friday',
                'fajr' => ['change' => 'weekly', 'roundMinutes' => '15', 'earliest' => '04:30', 'latest' => '06:45', 'beforeEndMinutes' => '30'],
                'dhuhr' => ['static' => '12:30', 'overrides' => [['condition' => 'daylightSavingsTime', 'time' => ['static' => '13:30']]]],
                'asr' => ['change' => 'weekly', 'roundMinutes' => '15', 'afterAthanMinutes' => '0'],
                'maghrib' => ['change' => 'daily', 'roundMinutes' => '1', 'afterAthanMinutes' => '10'],
                'isha' => ['change' => 'daily', 'roundMinutes' => '1', 'earliest' => '19:45', 'latest' => '23:30', 'afterAthanMinutes' => '10'],
            ],
        ];

        $location = new Location(47.777856, -122.190302, 'America/Los_Angeles', 'Y-m-d', 'H:i');

        $apiCasedResult = $this->buildCsv($location, CalculationMethod::fromArray($apiCased), '2026-10-29', '2026-11-12');
        $canonicalResult = $this->buildCsv($location, CalculationMethod::fromArray($canonical), '2026-10-29', '2026-11-12');

        $this->assertSame($canonicalResult, $apiCasedResult, 'API-cased configuration must produce identical output to canonical casing');

        // The method name must actually be applied (not silently falling back to MWL)
        $this->assertNotSame(
            $this->buildCsv($location, CalculationMethod::fromArray(array_merge($apiCased, ['name' => 'MWL'])), '2026-10-29', '2026-11-12'),
            $apiCasedResult,
            "The 'isna' method name must be honored, not treated as an unknown method"
        );
    }
}
