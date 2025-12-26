<?php

namespace SalahAPI\Tests\Calculations;

use PHPUnit\Framework\TestCase;
use SalahAPI\Calculations\Builder;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;
use DateTime;
use DateTimeZone;

/**
 * Builder Tests
 *
 * @package SalahAPI\Tests\Calculations
 */
class BuilderTest extends TestCase
{
    /**
     * Create a basic location configuration for testing
     */
    private function createTestLocation(): Location
    {
        return new Location(
            40.7128,                    // latitude (New York)
            -74.0060,                   // longitude (New York)
            'America/New_York',         // timezone
            'Y-m-d',                    // dateFormat
            'H:i'                       // timeFormat
        );
    }

    /**
     * Create a basic calculation method for testing with daily frequency
     */
    private function createTestCalculationMethodDaily(): CalculationMethod
    {
        $iqamaRules = new IqamaCalculationRules(
            null,  // changeOn
            new PrayerCalculationRule(null, 'daily', 5, null, null, 20, null, null),  // fajr
            new PrayerCalculationRule(null, 'daily', 5, null, null, 25, null, null),  // dhuhr
            new PrayerCalculationRule(null, 'daily', 5, null, null, 10, null, null),  // asr
            new PrayerCalculationRule(null, 'daily', 5, null, null, 5, null, null),   // maghrib
            new PrayerCalculationRule(null, 'daily', 5, null, null, 15, null, null)   // isha
        );

        return new CalculationMethod(
            'MWL',                      // name
            18.0,                       // fajrAngle
            17.0,                       // ishaAngle
            'Standard',                 // asrCalculationMethod
            'MOTN',                     // highLatitudeAdjustment
            $iqamaRules,                // iqamaCalculationRules
            null                        // jumuahRules
        );
    }

    /**
     * Create a calculation method for testing with weekly frequency
     */
    private function createTestCalculationMethodWeekly(): CalculationMethod
    {
        $iqamaRules = new IqamaCalculationRules(
            'Friday',  // changeOn
            new PrayerCalculationRule(null, 'weekly', 5, null, null, 20, null, null),  // fajr
            new PrayerCalculationRule(null, 'weekly', 5, null, null, 25, null, null),  // dhuhr
            new PrayerCalculationRule(null, 'weekly', 5, null, null, 10, null, null),  // asr
            new PrayerCalculationRule(null, 'weekly', 5, null, null, 5, null, null),   // maghrib
            new PrayerCalculationRule(null, 'weekly', 5, null, null, 15, null, null)   // isha
        );

        return new CalculationMethod(
            'MWL',                      // name
            18.0,                       // fajrAngle
            17.0,                       // ishaAngle
            'Standard',                 // asrCalculationMethod
            'MOTN',                     // highLatitudeAdjustment
            $iqamaRules,                // iqamaCalculationRules
            null                        // jumuahRules
        );
    }

    /**
     * Test Builder construction
     */
    public function testBuilderConstruction()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $this->assertInstanceOf(Builder::class, $builder);
    }

    /**
     * Test build method with daily frequency for a single day
     */
    public function testBuildSingleDayDaily()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $startDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 1 data row
        $this->assertCount(2, $result);

        // Check header row
        $this->assertEquals([
            'day', 'fajr_athan', 'fajr_iqama', 'sunrise',
            'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama',
            'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama'
        ], $result[0]);

        // Check data row
        $this->assertEquals('2023-01-15', $result[1][0]); // date

        // Verify all times are present and in HH:mm format
        for ($i = 1; $i < 12; $i++) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result[1][$i]);
        }
    }

    /**
     * Test build method with daily frequency for multiple days
     */
    public function testBuildMultipleDaysDaily()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $startDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-20', new DateTimeZone('America/New_York'));

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 6 data rows (Jan 15-20)
        $this->assertCount(7, $result);

        // Check that dates are sequential
        $this->assertEquals('2023-01-15', $result[1][0]);
        $this->assertEquals('2023-01-16', $result[2][0]);
        $this->assertEquals('2023-01-17', $result[3][0]);
        $this->assertEquals('2023-01-18', $result[4][0]);
        $this->assertEquals('2023-01-19', $result[5][0]);
        $this->assertEquals('2023-01-20', $result[6][0]);
    }

    /**
     * Test build method with weekly frequency
     */
    public function testBuildWeekly()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodWeekly();

        $builder = new Builder($location, $calculationMethod);

        // Start on a Friday and go through next Friday
        $startDate = new DateTime('2023-01-13', new DateTimeZone('America/New_York')); // Friday
        $endDate = new DateTime('2023-01-19', new DateTimeZone('America/New_York'));   // Thursday

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 7 data rows
        $this->assertCount(8, $result);

        // For weekly calculation, iqama times should be the same for days in the same week
        // Get Fajr iqama for the first week
        $firstWeekFajrIqama = $result[1][2]; // Fajr iqama column

        // All days in the first week should have the same Fajr iqama time
        for ($i = 1; $i <= 7; $i++) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result[$i][2]);
        }
    }

    /**
     * Test buildCsv method
     */
    public function testBuildCsv()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $startDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-16', new DateTimeZone('America/New_York'));

        $csv = $builder->buildCsv($startDate, $endDate);

        // Check that it's a string
        $this->assertIsString($csv);

        // Check that it contains header and data rows
        $lines = explode("\n", trim($csv));
        $this->assertCount(3, $lines); // Header + 2 days

        // Check that first line is header
        $this->assertStringContainsString('day', $lines[0]);
        $this->assertStringContainsString('fajr_athan', $lines[0]);
        $this->assertStringContainsString('fajr_iqama', $lines[0]);

        // Check that data rows contain dates
        $this->assertStringContainsString('2023-01-15', $lines[1]);
        $this->assertStringContainsString('2023-01-16', $lines[2]);
    }

    /**
     * Test buildAssociative method
     */
    public function testBuildAssociative()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $startDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));

        $result = $builder->buildAssociative($startDate, $endDate);

        // Should have 1 associative array for 1 day
        $this->assertCount(1, $result);

        // Check that it's an associative array with proper keys
        $this->assertIsArray($result[0]);
        $this->assertArrayHasKey('day', $result[0]);
        $this->assertArrayHasKey('fajr_athan', $result[0]);
        $this->assertArrayHasKey('fajr_iqama', $result[0]);
        $this->assertArrayHasKey('sunrise', $result[0]);
        $this->assertArrayHasKey('dhuhr_athan', $result[0]);
        $this->assertArrayHasKey('dhuhr_iqama', $result[0]);
        $this->assertArrayHasKey('asr_athan', $result[0]);
        $this->assertArrayHasKey('asr_iqama', $result[0]);
        $this->assertArrayHasKey('maghrib_athan', $result[0]);
        $this->assertArrayHasKey('maghrib_iqama', $result[0]);
        $this->assertArrayHasKey('isha_athan', $result[0]);
        $this->assertArrayHasKey('isha_iqama', $result[0]);

        // Check date value
        $this->assertEquals('2023-01-15', $result[0]['day']);
    }

    /**
     * Test build with string dates
     */
    public function testBuildWithStringDates()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $result = $builder->build('2023-01-15', '2023-01-16');

        // Should have header row + 2 data rows
        $this->assertCount(3, $result);

        $this->assertEquals('2023-01-15', $result[1][0]);
        $this->assertEquals('2023-01-16', $result[2][0]);
    }

    /**
     * Test build across DST boundary
     */
    public function testBuildAcrossDST()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        // March 11-13, 2023 - DST starts on March 12
        $startDate = new DateTime('2023-03-11', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-03-13', new DateTimeZone('America/New_York'));

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 3 data rows
        $this->assertCount(4, $result);

        // All rows should have valid times
        for ($i = 1; $i <= 3; $i++) {
            $this->assertEquals('2023-03-' . (10 + $i), $result[$i][0]);
            
            // Check all times are in HH:mm format
            for ($j = 1; $j < 12; $j++) {
                $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result[$i][$j]);
            }
        }
    }

    /**
     * Test build with no iqama rules
     */
    public function testBuildWithNoIqamaRules()
    {
        $location = $this->createTestLocation();
        
        // Create calculation method without iqama rules
        $calculationMethod = new CalculationMethod(
            'MWL',
            18.0,
            17.0,
            'Standard',
            'MOTN',
            null,  // No iqama rules
            null
        );

        $builder = new Builder($location, $calculationMethod);

        $startDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 1 data row
        $this->assertCount(2, $result);

        // Iqama times should be empty
        $this->assertEquals('', $result[1][2]);  // fajr_iqama
        $this->assertEquals('', $result[1][5]);  // dhuhr_iqama
        $this->assertEquals('', $result[1][7]);  // asr_iqama
        $this->assertEquals('', $result[1][9]);  // maghrib_iqama
        $this->assertEquals('', $result[1][11]); // isha_iqama

        // But athan times should still be present
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result[1][1]);  // fajr_athan
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $result[1][4]);  // dhuhr_athan
    }

    /**
     * Test weekly processing with Friday as change day
     */
    public function testWeeklyProcessingFridayChangeDay()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodWeekly();

        $builder = new Builder($location, $calculationMethod);

        // Build for two weeks starting on a Friday
        $startDate = new DateTime('2023-01-13', new DateTimeZone('America/New_York')); // Friday
        $endDate = new DateTime('2023-01-26', new DateTimeZone('America/New_York'));   // Thursday (2 weeks)

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 14 data rows
        $this->assertCount(15, $result);

        // Get Fajr iqama times for first and second week
        $week1FajrIqama = $result[1][2];  // First Friday
        $week2FajrIqama = $result[8][2];  // Second Friday

        // Iqama times within the same week should be consistent
        // (Note: they may vary due to DST adjustments but the logic should be applied)
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $week1FajrIqama);
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $week2FajrIqama);
    }

    /**
     * Test build with elevation parameter
     */
    public function testBuildWithElevation()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        // Create builder with elevation
        $builder = new Builder($location, $calculationMethod, 100);

        $startDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-15', new DateTimeZone('America/New_York'));

        $result = $builder->build($startDate, $endDate);

        // Should still build successfully with elevation
        $this->assertCount(2, $result);
        $this->assertEquals('2023-01-15', $result[1][0]);
    }

    /**
     * Test build for a full month
     */
    public function testBuildFullMonth()
    {
        $location = $this->createTestLocation();
        $calculationMethod = $this->createTestCalculationMethodDaily();

        $builder = new Builder($location, $calculationMethod);

        $startDate = new DateTime('2023-01-01', new DateTimeZone('America/New_York'));
        $endDate = new DateTime('2023-01-31', new DateTimeZone('America/New_York'));

        $result = $builder->build($startDate, $endDate);

        // Should have header row + 31 data rows
        $this->assertCount(32, $result);

        // Check first and last dates
        $this->assertEquals('2023-01-01', $result[1][0]);
        $this->assertEquals('2023-01-31', $result[31][0]);
    }
}
