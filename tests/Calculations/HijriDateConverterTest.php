<?php

namespace SalahAPI\Tests\Calculations;

use PHPUnit\Framework\TestCase;
use SalahAPI\Calculations\HijriDateConverter;
use DateTime;
use DateTimeZone;

/**
 * HijriDateConverter Tests
 *
 * Tests Hijri date conversion and Ramadan detection using real data from test.csv
 * generated with example-config.json settings.
 *
 * @package SalahAPI\Tests\Calculations
 */
class HijriDateConverterTest extends TestCase
{
    /**
     * Test converting Gregorian dates to Hijri dates
     */
    public function testConvertToHijri()
    {
        // Test dates with known Hijri equivalents (actual values from Umm al-Qura calendar)
        $testCases = [
            // January 1, 2026 = 12 Rajab 1447
            ['gregorian' => '2026-01-01', 'hijri' => ['day' => 12, 'month' => 7, 'year' => 1447]],
            // January 31, 2026 = 12 Sha'ban 1447
            ['gregorian' => '2026-01-31', 'hijri' => ['day' => 12, 'month' => 8, 'year' => 1447]],
            // March 1, 2026 = 12 Ramadan 1447 (in Ramadan!)
            ['gregorian' => '2026-03-01', 'hijri' => ['day' => 12, 'month' => 9, 'year' => 1447]],
            // June 1, 2026 = 15 Dhu al-Hijjah 1447
            ['gregorian' => '2026-06-01', 'hijri' => ['day' => 15, 'month' => 12, 'year' => 1447]],
            // December 31, 2026 = 22 Rajab 1448 (new Hijri year!)
            ['gregorian' => '2026-12-31', 'hijri' => ['day' => 22, 'month' => 7, 'year' => 1448]],
        ];
        
        foreach ($testCases as $testCase) {
            $date = new DateTime($testCase['gregorian']);
            $hijriDate = HijriDateConverter::convertToHijri($date);
            
            $this->assertEquals(
                $testCase['hijri']['day'],
                $hijriDate['day'],
                "Day mismatch for {$testCase['gregorian']}"
            );
            $this->assertEquals(
                $testCase['hijri']['month'],
                $hijriDate['month'],
                "Month mismatch for {$testCase['gregorian']}"
            );
            $this->assertEquals(
                $testCase['hijri']['year'],
                $hijriDate['year'],
                "Year mismatch for {$testCase['gregorian']}"
            );
        }
    }

    /**
     * Test converting Gregorian dates to Hijri with positive offset
     */
    public function testConvertToHijriWithPositiveOffset()
    {
        // Test with +1 day offset
        $date = new DateTime('2026-01-01');
        $hijriDate = HijriDateConverter::convertToHijri($date, 1);
        
        // Adding 1 day should advance the Hijri date by 1 day
        // 12 Rajab + 1 = 13 Rajab
        $this->assertEquals(13, $hijriDate['day']);
        $this->assertEquals(7, $hijriDate['month']);
        $this->assertEquals(1447, $hijriDate['year']);
    }

    /**
     * Test converting Gregorian dates to Hijri with negative offset
     */
    public function testConvertToHijriWithNegativeOffset()
    {
        // Test with -1 day offset
        $date = new DateTime('2026-01-01');
        $hijriDate = HijriDateConverter::convertToHijri($date, -1);
        
        // Subtracting 1 day should go back to previous day
        // 12 Rajab - 1 = 11 Rajab
        $this->assertEquals(11, $hijriDate['day']);
        $this->assertEquals(7, $hijriDate['month']);
        $this->assertEquals(1447, $hijriDate['year']);
    }

    /**
     * Test converting Gregorian dates to Hijri with offset that crosses month boundary
     */
    public function testConvertToHijriWithOffsetCrossingMonthBoundary()
    {
        // Test with +2 day offset on a date in Shawwal
        // March 30, 2026 = 11 Shawwal 1447
        $date = new DateTime('2026-03-30');
        $hijriDate = HijriDateConverter::convertToHijri($date, 2);
        
        // Adding 2 days should advance by 2 days
        // 11 Shawwal + 2 = 13 Shawwal
        $this->assertEquals(13, $hijriDate['day']);
        $this->assertEquals(10, $hijriDate['month']); // Shawwal
        $this->assertEquals(1447, $hijriDate['year']);
    }

    /**
     * Test Ramadan detection - dates NOT in Ramadan
     */
    public function testIsRamadanReturnsFalseForNonRamadanDates()
    {
        // Test dates from test.csv that are NOT in Ramadan
        $nonRamadanDates = [
            '2026-01-01',  // Rajab (month 7)
            '2026-02-01',  // Sha'ban (month 8)
            '2026-03-20',  // Shawwal (month 10) - Ramadan ended on March 19
            '2026-05-01',  // Dhu al-Qi'dah (month 11)
            '2026-06-01',  // Shawwal (month 10)
            '2026-12-31',  // Dhu al-Hijjah (month 12)
        ];
        
        foreach ($nonRamadanDates as $dateStr) {
            $date = new DateTime($dateStr);
            $isRamadan = HijriDateConverter::isRamadan($date);
            
            $this->assertFalse(
                $isRamadan,
                "Date {$dateStr} should not be in Ramadan"
            );
        }
    }

    /**
     * Test Ramadan detection - dates IN Ramadan
     * 
     * According to the Umm al-Qura calendar, Ramadan 1447 runs from
     * February 18 to March 19, 2026.
     */
    public function testIsRamadanReturnsTrueForRamadanDates()
    {
        // These dates should be in Ramadan 1447 (month 9)
        // Ramadan 1447: February 18 - March 19, 2026
        $ramadanDates = [
            '2026-02-18',  // First day of Ramadan
            '2026-02-25',
            '2026-03-01',  // Mid-Ramadan
            '2026-03-10',
            '2026-03-15',
            '2026-03-19',  // Last day of Ramadan
        ];
        
        foreach ($ramadanDates as $dateStr) {
            $date = new DateTime($dateStr);
            $isRamadan = HijriDateConverter::isRamadan($date);
            
            $this->assertTrue(
                $isRamadan,
                "Date {$dateStr} should be in Ramadan"
            );
        }
    }

    /**
     * Test Ramadan detection with offset
     */
    public function testIsRamadanWithOffset()
    {
        // Test date just before Ramadan starts (February 17, 2026)
        $dateBeforeRamadan = new DateTime('2026-02-17');
        
        // Without offset, should not be Ramadan
        $this->assertFalse(
            HijriDateConverter::isRamadan($dateBeforeRamadan, 0),
            "February 17, 2026 should not be Ramadan"
        );
        
        // With +1 offset, should be Ramadan (moves to Feb 18 which is Ramadan)
        $this->assertTrue(
            HijriDateConverter::isRamadan($dateBeforeRamadan, 1),
            "February 17, 2026 with +1 offset should be Ramadan"
        );
    }

    /**
     * Test Ramadan detection across timezone boundaries
     */
    public function testIsRamadanAcrossTimezones()
    {
        // Test the same Gregorian date in different timezones
        // Since Hijri conversion is based on the date, not the time or timezone,
        // the same date should give the same Ramadan status
        $dateStrSeattle = '2026-03-01 00:00:00';
        $dateSeattle = new DateTime($dateStrSeattle, new DateTimeZone('America/Los_Angeles'));
        
        $dateStrNewYork = '2026-03-01 00:00:00';
        $dateNewYork = new DateTime($dateStrNewYork, new DateTimeZone('America/New_York'));
        
        $isRamadanSeattle = HijriDateConverter::isRamadan($dateSeattle);
        $isRamadanNewYork = HijriDateConverter::isRamadan($dateNewYork);
        
        // Both should have the same Ramadan status (both should be true for March 1)
        $this->assertEquals(
            $isRamadanSeattle,
            $isRamadanNewYork,
            "Same Gregorian date should have same Ramadan status regardless of timezone"
        );
    }

    /**
     * Test Hijri date conversion for dates at year boundaries
     */
    public function testConvertToHijriAtYearBoundary()
    {
        // Test dates at the boundary of Hijri years
        $testCases = [
            // End of Hijri year 1446 (around mid-2025)
            ['gregorian' => '2025-06-15', 'hijri' => ['year' => 1446]],
            // Start of Hijri year 1447
            ['gregorian' => '2025-07-01', 'hijri' => ['year' => 1447]],
            // End of Hijri year 1447 (around mid-2026)
            ['gregorian' => '2026-06-15', 'hijri' => ['year' => 1447]],
        ];
        
        foreach ($testCases as $testCase) {
            $date = new DateTime($testCase['gregorian']);
            $hijriDate = HijriDateConverter::convertToHijri($date);
            
            $this->assertEquals(
                $testCase['hijri']['year'],
                $hijriDate['year'],
                "Year mismatch for {$testCase['gregorian']}"
            );
        }
    }

    /**
     * Test that Hijri month numbers are valid (1-12)
     */
    public function testConvertToHijriReturnsValidMonthNumbers()
    {
        // Test dates throughout the year
        $testDates = [
            '2026-01-01', '2026-02-01', '2026-03-01', '2026-04-01',
            '2026-05-01', '2026-06-01', '2026-07-01', '2026-08-01',
            '2026-09-01', '2026-10-01', '2026-11-01', '2026-12-01',
        ];
        
        foreach ($testDates as $dateStr) {
            $date = new DateTime($dateStr);
            $hijriDate = HijriDateConverter::convertToHijri($date);
            
            $this->assertGreaterThanOrEqual(
                1,
                $hijriDate['month'],
                "Month should be >= 1 for {$dateStr}"
            );
            $this->assertLessThanOrEqual(
                12,
                $hijriDate['month'],
                "Month should be <= 12 for {$dateStr}"
            );
        }
    }

    /**
     * Test that Hijri day numbers are valid (1-30)
     */
    public function testConvertToHijriReturnsValidDayNumbers()
    {
        // Test dates throughout the year
        $testDates = [
            '2026-01-01', '2026-02-15', '2026-03-30', '2026-04-10',
            '2026-05-20', '2026-06-25', '2026-07-05', '2026-08-15',
            '2026-09-10', '2026-10-20', '2026-11-25', '2026-12-31',
        ];
        
        foreach ($testDates as $dateStr) {
            $date = new DateTime($dateStr);
            $hijriDate = HijriDateConverter::convertToHijri($date);
            
            $this->assertGreaterThanOrEqual(
                1,
                $hijriDate['day'],
                "Day should be >= 1 for {$dateStr}"
            );
            $this->assertLessThanOrEqual(
                30,
                $hijriDate['day'],
                "Day should be <= 30 for {$dateStr}"
            );
        }
    }

    /**
     * Test Ramadan boundary detection - dates just before and after Ramadan
     */
    public function testRamadanBoundaryDetection()
    {
        // We know Ramadan 1447 starts on February 18, 2026
        $ramadanStartDate = new DateTime('2026-02-18');
        
        $this->assertTrue(
            HijriDateConverter::isRamadan($ramadanStartDate),
            "February 18, 2026 should be first day of Ramadan"
        );
        
        // Day before should not be Ramadan
        $dayBefore = new DateTime('2026-02-17');
        $this->assertFalse(
            HijriDateConverter::isRamadan($dayBefore),
            "Day before Ramadan (Feb 17) should not be Ramadan"
        );
        
        // Day after should still be Ramadan
        $dayAfter = new DateTime('2026-02-19');
        $this->assertTrue(
            HijriDateConverter::isRamadan($dayAfter),
            "Day after Ramadan start (Feb 19) should be Ramadan"
        );
        
        // Last day of Ramadan should be Ramadan
        $lastDay = new DateTime('2026-03-19');
        $this->assertTrue(
            HijriDateConverter::isRamadan($lastDay),
            "March 19, 2026 should be last day of Ramadan"
        );
        
        // Day after Ramadan ends should not be Ramadan
        $dayAfterEnd = new DateTime('2026-03-20');
        $this->assertFalse(
            HijriDateConverter::isRamadan($dayAfterEnd),
            "Day after Ramadan ends (Mar 20) should not be Ramadan"
        );
    }

    /**
     * Test that consecutive days increment properly in Hijri calendar
     */
    public function testConsecutiveDaysIncrementProperly()
    {
        // Test that adding 1 day to a Gregorian date adds 1 day to the Hijri date
        // (unless it crosses a month boundary)
        $date1 = new DateTime('2026-01-15');
        $date2 = new DateTime('2026-01-16');
        
        $hijri1 = HijriDateConverter::convertToHijri($date1);
        $hijri2 = HijriDateConverter::convertToHijri($date2);
        
        // Should be consecutive days
        if ($hijri1['month'] === $hijri2['month']) {
            // Same month, day should increment by 1
            $this->assertEquals(
                $hijri1['day'] + 1,
                $hijri2['day'],
                "Consecutive days in same month should increment by 1"
            );
        }
    }

    /**
     * Test leap year handling
     */
    public function testLeapYearHandling()
    {
        // Test conversion during a Gregorian leap year
        // 2024 is a leap year, 2026 is not
        $leapYearDate = new DateTime('2024-02-29'); // Leap day
        $normalYearDate = new DateTime('2026-02-28');
        
        // Both should convert successfully
        $hijriLeap = HijriDateConverter::convertToHijri($leapYearDate);
        $hijriNormal = HijriDateConverter::convertToHijri($normalYearDate);
        
        $this->assertIsArray($hijriLeap);
        $this->assertIsArray($hijriNormal);
        $this->assertArrayHasKey('day', $hijriLeap);
        $this->assertArrayHasKey('day', $hijriNormal);
    }

    /**
     * Test conversion consistency over a full year
     */
    public function testConversionConsistencyOverFullYear()
    {
        // Test that converting all days in 2026 produces valid results
        $startDate = new DateTime('2026-01-01');
        $endDate = new DateTime('2026-12-31');
        
        $currentDate = clone $startDate;
        $previousHijri = null;
        
        while ($currentDate <= $endDate) {
            $hijriDate = HijriDateConverter::convertToHijri($currentDate);
            
            // Verify all fields are present and valid
            $this->assertArrayHasKey('day', $hijriDate);
            $this->assertArrayHasKey('month', $hijriDate);
            $this->assertArrayHasKey('year', $hijriDate);
            
            $this->assertIsInt($hijriDate['day']);
            $this->assertIsInt($hijriDate['month']);
            $this->assertIsInt($hijriDate['year']);
            
            // Verify values are in valid ranges
            $this->assertGreaterThanOrEqual(1, $hijriDate['day']);
            $this->assertLessThanOrEqual(30, $hijriDate['day']);
            $this->assertGreaterThanOrEqual(1, $hijriDate['month']);
            $this->assertLessThanOrEqual(12, $hijriDate['month']);
            
            $previousHijri = $hijriDate;
            $currentDate->modify('+1 day');
        }
    }
}
