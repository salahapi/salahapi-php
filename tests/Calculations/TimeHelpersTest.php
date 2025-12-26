<?php

namespace SalahAPI\Tests\Calculations;

use PHPUnit\Framework\TestCase;
use SalahAPI\Calculations\TimeHelpers;
use DateTime;
use DateTimeZone;

/**
 * TimeHelpers Tests
 *
 * @package SalahAPI\Tests\Calculations
 */
class TimeHelpersTest extends TestCase
{
    /**
     * Test the time to minutes conversion function
     */
    public function testTimeToMinutes()
    {
        // Create test DateTime objects
        $time1 = new DateTime('2023-01-01 05:30:00', new DateTimeZone('UTC')); // 5:30 AM
        $time2 = new DateTime('2023-01-01 13:45:00', new DateTimeZone('UTC')); // 1:45 PM
        $time3 = new DateTime('2023-01-01 23:15:00', new DateTimeZone('UTC')); // 11:15 PM
        $time4 = new DateTime('2023-01-01 00:00:00', new DateTimeZone('UTC')); // Midnight

        // Test with standard time (non-DST)
        $this->assertEquals(330, TimeHelpers::timeToMinutes($time1)); // 5:30 = (5*60) + 30 = 330
        $this->assertEquals(825, TimeHelpers::timeToMinutes($time2)); // 13:45 = (13*60) + 45 = 825
        $this->assertEquals(1395, TimeHelpers::timeToMinutes($time3)); // 23:15 = (23*60) + 15 = 1395
        $this->assertEquals(0, TimeHelpers::timeToMinutes($time4)); // 00:00 = 0
    }

    /**
     * Test the time to minutes conversion function during DST
     */
    public function testTimeToMinutesDuringDST()
    {
        // Create a DateTime with DST in effect
        $dst_time = new DateTime('2023-07-01 14:30:00', new DateTimeZone('America/New_York'));
        
        // Verify DST is active
        $this->assertEquals(1, $dst_time->format('I'));
        
        // Time in minutes should be adjusted for DST (subtract 60 minutes)
        // 14:30 = 870 minutes, minus 60 for DST = 810 minutes
        $this->assertEquals(810, TimeHelpers::timeToMinutes($dst_time));
    }

    /**
     * Test rounding down time to nearest X minutes
     */
    public function testRoundDown()
    {
        // Test with no rounding (default 1 minute)
        $time = new DateTime('2023-01-01 13:45:30', new DateTimeZone('UTC'));
        $result = TimeHelpers::roundDown($time);
        $this->assertEquals('13:45:30', $result->format('H:i:s'));
        $this->assertEquals($time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged

        // Test with 5 minute rounding
        $time = new DateTime('2023-01-01 13:47:30');
        $result = TimeHelpers::roundDown($time, 5);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test with 15 minute rounding
        $time = new DateTime('2023-01-01 13:59:59');
        $result = TimeHelpers::roundDown($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test exact match to interval (should stay the same)
        $time = new DateTime('2023-01-01 13:45:00');
        $result = TimeHelpers::roundDown($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test hour change
        $time = new DateTime('2023-01-01 14:01:30');
        $result = TimeHelpers::roundDown($time, 30);
        $this->assertEquals('14:00:00', $result->format('H:i:s'));
    }

    /**
     * Test rounding up time to nearest X minutes
     */
    public function testRoundUp()
    {
        // Test with no rounding (default 1 minute)
        $time = new DateTime('2023-01-01 13:45:30', new DateTimeZone('UTC'));
        $result = TimeHelpers::roundUp($time);
        $this->assertEquals('13:45:30', $result->format('H:i:s')); // No change
        $this->assertEquals($time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged

        // Test with 5 minute rounding
        $time = new DateTime('2023-01-01 13:42:01');
        $result = TimeHelpers::roundUp($time, 5);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test with 15 minute rounding
        $time = new DateTime('2023-01-01 13:31:00');
        $result = TimeHelpers::roundUp($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test exact match to interval (should stay the same)
        $time = new DateTime('2023-01-01 13:45:00');
        $result = TimeHelpers::roundUp($time, 15);
        $this->assertEquals('13:45:00', $result->format('H:i:s'));

        // Test hour change
        $time = new DateTime('2023-01-01 13:59:30');
        $result = TimeHelpers::roundUp($time, 5);
        $this->assertEquals('14:00:00', $result->format('H:i:s'));
    }

    /**
     * Test normalizing time for DST
     */
    public function testNormalizeTimeForDst()
    {
        // Test with non-DST time
        $standard_time = new DateTime('2023-01-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(0, $standard_time->format('I')); // Verify it's not DST
        
        $result = TimeHelpers::normalizeTimeForDst($standard_time);
        $this->assertEquals('13:30:00', $result->format('H:i:s')); // No change for non-DST
        $this->assertEquals($standard_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($standard_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
        
        // Test with DST time
        $dst_time = new DateTime('2023-07-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(1, $dst_time->format('I')); // Verify it's DST
        
        $result = TimeHelpers::normalizeTimeForDst($dst_time);
        $this->assertEquals('12:30:00', $result->format('H:i:s')); // Should subtract 1 hour
        $this->assertEquals($dst_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($dst_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
    }

    /**
     * Test denormalizing time for DST
     */
    public function testDenormalizeTimeForDst()
    {
        // Test with non-DST time
        $standard_time = new DateTime('2023-01-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(0, $standard_time->format('I')); // Verify it's not DST
        
        $result = TimeHelpers::denormalizeTimeForDst($standard_time);
        $this->assertEquals('13:30:00', $result->format('H:i:s')); // No change for non-DST
        $this->assertEquals($standard_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($standard_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
        
        // Test with DST time
        $dst_time = new DateTime('2023-07-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(1, $dst_time->format('I')); // Verify it's DST
        
        $result = TimeHelpers::denormalizeTimeForDst($dst_time);
        $this->assertEquals('14:30:00', $result->format('H:i:s')); // Should add 1 hour
        $this->assertEquals($dst_time->format('Y-m-d'), $result->format('Y-m-d')); // Date unchanged
        $this->assertEquals($dst_time->getTimezone()->getName(), $result->getTimezone()->getName()); // Timezone unchanged
    }

    /**
     * Test normalizing and denormalizing time for DST in sequence
     */
    public function testNormalizeAndDenormalizeTimeForDst()
    {
        // Test with DST time
        $original_time = new DateTime('2023-07-15 13:30:00', new DateTimeZone('America/New_York'));
        $this->assertEquals(1, $original_time->format('I')); // Verify it's DST
        
        // Normalize (subtract hour)
        $normalized = TimeHelpers::normalizeTimeForDst($original_time);
        $this->assertEquals('12:30:00', $normalized->format('H:i:s'));
        
        // Denormalize (add hour back)
        $denormalized = TimeHelpers::denormalizeTimeForDst($normalized);
        $this->assertEquals('13:30:00', $denormalized->format('H:i:s'));
        $this->assertEquals($original_time->format('H:i:s'), $denormalized->format('H:i:s')); // Should match original
    }

    /**
     * Test normalizing times in days_data structure
     */
    public function testNormalizeTimesForDst()
    {
        // Create test days_data with both DST and non-DST dates
        $days_data = [
            [
                'date' => new DateTime('2023-03-11 00:00:00', new DateTimeZone('America/New_York')),
                'athan' => [
                    'fajr' => new DateTime('2023-03-11 06:30:00', new DateTimeZone('America/New_York')),
                    'dhuhr' => new DateTime('2023-03-11 12:00:00', new DateTimeZone('America/New_York')),
                ]
            ],
            [
                'date' => new DateTime('2023-07-15 00:00:00', new DateTimeZone('America/New_York')),
                'athan' => [
                    'fajr' => new DateTime('2023-07-15 06:30:00', new DateTimeZone('America/New_York')),
                    'dhuhr' => new DateTime('2023-07-15 12:00:00', new DateTimeZone('America/New_York')),
                ]
            ]
        ];
        
        // Normalize using TimeHelpers
        $normalized = TimeHelpers::normalizeTimesForDst($days_data);
        
        // Check day 0 (no DST) - should remain unchanged
        $this->assertEquals('06:30:00', $normalized[0]['athan']['fajr']->format('H:i:s'));
        $this->assertEquals('12:00:00', $normalized[0]['athan']['dhuhr']->format('H:i:s'));
        
        // Check day 1 (DST) - should be reduced by 1 hour
        $this->assertEquals('05:30:00', $normalized[1]['athan']['fajr']->format('H:i:s'));
        $this->assertEquals('11:00:00', $normalized[1]['athan']['dhuhr']->format('H:i:s'));
    }

    /**
     * Test parseTimeString
     */
    public function testParseTimeString()
    {
        $baseDate = new DateTime('2023-07-15 00:00:00', new DateTimeZone('America/New_York'));
        
        // Test basic parsing
        $result = TimeHelpers::parseTimeString($baseDate, '13:30');
        $this->assertEquals('13:30:00', $result->format('H:i:s'));
        $this->assertEquals('2023-07-15', $result->format('Y-m-d'));
        
        // Test with different times
        $result = TimeHelpers::parseTimeString($baseDate, '05:15');
        $this->assertEquals('05:15:00', $result->format('H:i:s'));
        
        $result = TimeHelpers::parseTimeString($baseDate, '23:59');
        $this->assertEquals('23:59:00', $result->format('H:i:s'));
        
        $result = TimeHelpers::parseTimeString($baseDate, '00:00');
        $this->assertEquals('00:00:00', $result->format('H:i:s'));
    }

    /**
     * Test parseTimeString with invalid format
     */
    public function testParseTimeStringInvalidFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        
        $baseDate = new DateTime('2023-07-15 00:00:00', new DateTimeZone('America/New_York'));
        TimeHelpers::parseTimeString($baseDate, 'invalid');
    }

    /**
     * Test converting Western numerals to Arabic numerals
     */
    public function testConvertToArabicNumerals()
    {
        // Test with integer
        $this->assertEquals('١٢٣٤٥', TimeHelpers::convertToArabicNumerals(12345));
        
        // Test with string
        $this->assertEquals('٦٧٨٩٠', TimeHelpers::convertToArabicNumerals('67890'));
        
        // Test with mixed string
        $this->assertEquals('Prayer time: ٥:٣٠', TimeHelpers::convertToArabicNumerals('Prayer time: 5:30'));
        
        // Test with floating point number
        $this->assertEquals('٣.١٤', TimeHelpers::convertToArabicNumerals(3.14));
        
        // Test with string containing non-numeric characters
        $this->assertEquals(
            'Fajr: ٠٥:١٥, Dhuhr: ١٢:٣٠', 
            TimeHelpers::convertToArabicNumerals('Fajr: 05:15, Dhuhr: 12:30')
        );
        
        // Test with empty string
        $this->assertEquals('', TimeHelpers::convertToArabicNumerals(''));
        
        // Test with string containing no numerals
        $this->assertEquals('No numbers here', TimeHelpers::convertToArabicNumerals('No numbers here'));
        
        // Test with zero
        $this->assertEquals('٠', TimeHelpers::convertToArabicNumerals(0));
    }
}
