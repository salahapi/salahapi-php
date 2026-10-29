<?php

namespace SalahAPI\Tests\Calculations;

use PHPUnit\Framework\TestCase;
use SalahAPI\Calculations\IqamaCalculator;
use SalahAPI\Calculations\Method;
use SalahAPI\Calculations\PrayerTimes;
use SalahAPI\PrayerCalculationOverrideRule;
use SalahAPI\PrayerCalculationRule;
use DateTime;
use DateTimeZone;

/**
 * Validates Iqama time calculation around the DST changes against production data.
 *
 * Fall change: clocks fall back on Sunday, November 1, 2026 at 2:00 AM in
 * America/Los_Angeles (prayer_times_db_2026-10-29.csv).
 *
 * Spring change: clocks spring forward on Sunday, March 14, 2027 at 2:00 AM in
 * America/Los_Angeles (prayer_times_db_2026-10-29_2.csv).
 *
 * Expected values are taken from the production output and the calculation rules
 * mirror the live configuration served by:
 * https://bothellmosque.org/wp-json/muslim-prayer-times/v1/salah-api
 *
 * The Asr Iqama is driven by the Hanafi Asr Athan (asrIqamaCalculationMethod:
 * "hanafi"), which is not part of the CSV export. Those Athan times are calculated
 * with PrayerTimes using the API configuration (ISNA method, middleOfTheNight
 * high-latitude adjustment, Bothell coordinates). The calculated standard Asr
 * Athan is validated against the CSV's asr_athan column first, so the same engine
 * can be trusted to produce the Hanafi times.
 *
 * @package SalahAPI\Tests\Calculations
 */
class IqamaCalculatorDstChangeTest extends TestCase
{
    private const TIMEZONE = 'America/Los_Angeles';

    private const LATITUDE = 47.777856;
    private const LONGITUDE = -122.190302;

    /**
     * Production rows around the fall change (prayer_times_db_2026-10-29.csv).
     *
     * Columns: day, fajr_athan, fajr_iqama, sunrise, dhuhr_athan, dhuhr_iqama,
     * asr_athan, asr_iqama, maghrib_athan, maghrib_iqama, isha_athan, isha_iqama
     */
    private const FALL_CSV_ROWS = [
        ['10/29/26', '6:22 AM', '6:45 AM', '7:49 AM', '12:52 PM', '1:30 PM', '3:28 PM', '4:30 PM', '5:56 PM', '6:06 PM', '7:22 PM', '7:45 PM'],
        ['10/30/26', '6:24 AM', '6:45 AM', '7:50 AM', '12:52 PM', '1:30 PM', '3:27 PM', '4:15 PM', '5:55 PM', '6:05 PM', '7:20 PM', '7:45 PM'],
        ['10/31/26', '6:25 AM', '6:45 AM', '7:52 AM', '12:52 PM', '1:30 PM', '3:26 PM', '4:15 PM', '5:53 PM', '6:03 PM', '7:19 PM', '7:45 PM'],
        ['11/1/26', '5:27 AM', '6:15 AM', '6:53 AM', '11:52 AM', '12:30 PM', '2:25 PM', '3:15 PM', '4:51 PM', '5:01 PM', '6:17 PM', '7:45 PM'],
        ['11/2/26', '5:28 AM', '6:15 AM', '6:55 AM', '11:52 AM', '12:30 PM', '2:23 PM', '3:15 PM', '4:50 PM', '5:00 PM', '6:16 PM', '7:45 PM'],
        ['11/3/26', '5:29 AM', '6:15 AM', '6:57 AM', '11:52 AM', '12:30 PM', '2:22 PM', '3:15 PM', '4:48 PM', '4:58 PM', '6:15 PM', '7:45 PM'],
        ['11/4/26', '5:31 AM', '6:15 AM', '6:58 AM', '11:52 AM', '12:30 PM', '2:21 PM', '3:15 PM', '4:47 PM', '4:57 PM', '6:13 PM', '7:45 PM'],
        ['11/5/26', '5:32 AM', '6:15 AM', '7:00 AM', '11:52 AM', '12:30 PM', '2:20 PM', '3:15 PM', '4:45 PM', '4:55 PM', '6:12 PM', '7:45 PM'],
        ['11/6/26', '5:33 AM', '6:30 AM', '7:01 AM', '11:52 AM', '12:30 PM', '2:19 PM', '3:00 PM', '4:44 PM', '4:54 PM', '6:11 PM', '7:45 PM'],
        ['11/7/26', '5:35 AM', '6:30 AM', '7:03 AM', '11:52 AM', '12:30 PM', '2:18 PM', '3:00 PM', '4:43 PM', '4:53 PM', '6:10 PM', '7:45 PM'],
        ['11/8/26', '5:36 AM', '6:30 AM', '7:04 AM', '11:53 AM', '12:30 PM', '2:17 PM', '3:00 PM', '4:41 PM', '4:51 PM', '6:08 PM', '7:45 PM'],
        ['11/9/26', '5:37 AM', '6:30 AM', '7:06 AM', '11:53 AM', '12:30 PM', '2:15 PM', '3:00 PM', '4:40 PM', '4:50 PM', '6:07 PM', '7:45 PM'],
        ['11/10/26', '5:39 AM', '6:30 AM', '7:07 AM', '11:53 AM', '12:30 PM', '2:14 PM', '3:00 PM', '4:39 PM', '4:49 PM', '6:06 PM', '7:45 PM'],
        ['11/11/26', '5:40 AM', '6:30 AM', '7:09 AM', '11:53 AM', '12:30 PM', '2:13 PM', '3:00 PM', '4:37 PM', '4:47 PM', '6:05 PM', '7:45 PM'],
        ['11/12/26', '5:41 AM', '6:30 AM', '7:10 AM', '11:53 AM', '12:30 PM', '2:12 PM', '3:00 PM', '4:36 PM', '4:46 PM', '6:04 PM', '7:45 PM'],
    ];

    /**
     * Production rows around the spring change (prayer_times_db_2026-10-29_2.csv).
     *
     * Columns: day, fajr_athan, fajr_iqama, sunrise, dhuhr_athan, dhuhr_iqama,
     * asr_athan, asr_iqama, maghrib_athan, maghrib_iqama, isha_athan, isha_iqama
     */
    private const SPRING_CSV_ROWS = [
        ['3/9/27', '5:09 AM', '6:00 AM', '6:34 AM', '12:19 PM', '12:30 PM', '3:27 PM', '4:30 PM', '6:06 PM', '6:16 PM', '7:30 PM', '7:45 PM'],
        ['3/10/27', '5:07 AM', '6:00 AM', '6:32 AM', '12:19 PM', '12:30 PM', '3:28 PM', '4:30 PM', '6:08 PM', '6:18 PM', '7:32 PM', '7:45 PM'],
        ['3/11/27', '5:05 AM', '6:00 AM', '6:30 AM', '12:19 PM', '12:30 PM', '3:29 PM', '4:30 PM', '6:09 PM', '6:19 PM', '7:33 PM', '7:45 PM'],
        ['3/12/27', '5:03 AM', '5:45 AM', '6:28 AM', '12:18 PM', '12:30 PM', '3:30 PM', '4:30 PM', '6:11 PM', '6:21 PM', '7:35 PM', '7:45 PM'],
        ['3/13/27', '5:01 AM', '5:45 AM', '6:26 AM', '12:18 PM', '12:30 PM', '3:31 PM', '4:30 PM', '6:12 PM', '6:22 PM', '7:36 PM', '7:46 PM'],
        ['3/14/27', '5:59 AM', '6:45 AM', '7:24 AM', '1:18 PM', '1:30 PM', '4:32 PM', '5:30 PM', '7:14 PM', '7:24 PM', '8:38 PM', '8:48 PM'],
        ['3/15/27', '5:57 AM', '6:45 AM', '7:22 AM', '1:18 PM', '1:30 PM', '4:33 PM', '5:30 PM', '7:15 PM', '7:25 PM', '8:39 PM', '8:49 PM'],
        ['3/16/27', '5:55 AM', '6:45 AM', '7:20 AM', '1:17 PM', '1:30 PM', '4:34 PM', '5:30 PM', '7:17 PM', '7:27 PM', '8:41 PM', '8:51 PM'],
        ['3/17/27', '5:53 AM', '6:45 AM', '7:18 AM', '1:17 PM', '1:30 PM', '4:35 PM', '5:30 PM', '7:18 PM', '7:28 PM', '8:42 PM', '8:52 PM'],
        ['3/18/27', '5:51 AM', '6:45 AM', '7:16 AM', '1:17 PM', '1:30 PM', '4:36 PM', '5:30 PM', '7:20 PM', '7:30 PM', '8:44 PM', '8:54 PM'],
        ['3/19/27', '5:48 AM', '6:30 AM', '7:14 AM', '1:16 PM', '1:30 PM', '4:36 PM', '5:45 PM', '7:21 PM', '7:31 PM', '8:46 PM', '8:56 PM'],
        ['3/20/27', '5:46 AM', '6:30 AM', '7:12 AM', '1:16 PM', '1:30 PM', '4:37 PM', '5:45 PM', '7:22 PM', '7:32 PM', '8:47 PM', '8:57 PM'],
        ['3/21/27', '5:44 AM', '6:30 AM', '7:10 AM', '1:16 PM', '1:30 PM', '4:38 PM', '5:45 PM', '7:24 PM', '7:34 PM', '8:49 PM', '8:59 PM'],
        ['3/22/27', '5:42 AM', '6:30 AM', '7:08 AM', '1:16 PM', '1:30 PM', '4:39 PM', '5:45 PM', '7:25 PM', '7:35 PM', '8:50 PM', '9:00 PM'],
        ['3/23/27', '5:40 AM', '6:30 AM', '7:06 AM', '1:15 PM', '1:30 PM', '4:40 PM', '5:45 PM', '7:27 PM', '7:37 PM', '8:52 PM', '9:02 PM'],
        ['3/24/27', '5:37 AM', '6:30 AM', '7:04 AM', '1:15 PM', '1:30 PM', '4:41 PM', '5:45 PM', '7:28 PM', '7:38 PM', '8:54 PM', '9:04 PM'],
        ['3/25/27', '5:35 AM', '6:30 AM', '7:02 AM', '1:15 PM', '1:30 PM', '4:41 PM', '5:45 PM', '7:30 PM', '7:40 PM', '8:55 PM', '9:05 PM'],
    ];

    /**
     * Build days_data (same shape Builder produces) from CSV rows.
     *
     * Dates are anchored at midnight like Builder::build does; Athan times are
     * parsed in the location timezone so all CSV times (which are after 2:00 AM,
     * even on the transition days) resolve to the post-transition DST regime.
     *
     * @param array<int, array> $rows Rows of one of the CSV fixtures
     * @return array<string, array> Days data keyed by Y-m-d
     */
    private static function daysDataFromRows(array $rows): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);
        $daysData = [];

        foreach ($rows as $row) {
            $date = DateTime::createFromFormat('!m/d/y', $row[0], $tz);
            $dayKey = $date->format('Y-m-d');

            $daysData[$dayKey] = [
                'date' => new DateTime($dayKey . ' 00:00:00', $tz),
                'athan' => [
                    'fajr' => DateTime::createFromFormat('Y-m-d g:i A', $dayKey . ' ' . $row[1], $tz),
                    'sunrise' => DateTime::createFromFormat('Y-m-d g:i A', $dayKey . ' ' . $row[3], $tz),
                    'dhuhr' => DateTime::createFromFormat('Y-m-d g:i A', $dayKey . ' ' . $row[4], $tz),
                    'maghrib' => DateTime::createFromFormat('Y-m-d g:i A', $dayKey . ' ' . $row[8], $tz),
                    'isha' => DateTime::createFromFormat('Y-m-d g:i A', $dayKey . ' ' . $row[10], $tz),
                ],
            ];
        }

        return $daysData;
    }

    /**
     * All days of a CSV fixture as days_data
     *
     * @param array<int, array> $rows Rows of one of the CSV fixtures
     * @return array<string, array>
     */
    private static function allDaysData(array $rows): array
    {
        return self::daysDataFromRows($rows);
    }

    /**
     * Partition days into weekly batches starting on Friday, mirroring
     * Builder::processWeekly with changeOn "Friday" from the API configuration.
     *
     * @param array<string, array> $daysData
     * @return array<int, array> Weekly batches keyed by Y-m-d
     */
    private static function weekBatches(array $daysData): array
    {
        $batches = [];
        $currentBatch = [];

        foreach ($daysData as $dayKey => $dayData) {
            $dayNumber = (int)$dayData['date']->format('w');

            if ($dayNumber === 5 || $currentBatch === []) { // Friday starts a new week
                if ($currentBatch !== []) {
                    $batches[] = $currentBatch;
                }
                $currentBatch = [];
            }

            $currentBatch[$dayKey] = $dayData;

            if ($dayNumber === 4) { // Thursday is the last day of the week
                $batches[] = $currentBatch;
                $currentBatch = [];
            }
        }

        if ($currentBatch !== []) {
            $batches[] = $currentBatch;
        }

        return $batches;
    }

    /**
     * Calculate Iqama times for a prayer across the given CSV rows, batching
     * weekly rules per week like Builder does.
     *
     * @param string $prayerName Prayer to calculate
     * @param array<int, array> $rows Rows of one of the CSV fixtures
     * @param string|null $endPrayerName End prayer for beforeEndMinutes rules
     * @return array<string, string> Iqama times keyed by Y-m-d, formatted like the CSV (g:i A)
     */
    private static function calculateIqamaTimes(string $prayerName, array $rows, ?string $endPrayerName = null): array
    {
        $results = [];

        foreach (self::weekBatches(self::allDaysData($rows)) as $batch) {
            $results += IqamaCalculator::calculateIqama(
                $batch,
                $prayerName,
                self::ruleFor($prayerName),
                $endPrayerName
            );
        }

        ksort($results);

        $formatted = [];
        foreach ($results as $dayKey => $iqama) {
            $formatted[$dayKey] = $iqama->format('g:i A');
        }

        return $formatted;
    }

    /**
     * Calculation rules mirroring iqamaCalculationRules from
     * https://bothellmosque.org/wp-json/muslim-prayer-times/v1/salah-api
     */
    private static function ruleFor(string $prayerName): PrayerCalculationRule
    {
        switch ($prayerName) {
            case 'fajr':
                // {"change":"weekly","roundMinutes":"15","earliest":"04:30","latest":"06:45",
                //  "beforeEndMinutes":30,"overrides":[{"condition":"ramadan","time":{
                //  "change":"daily","afterAthanMinutes":15,"roundMinutes":"1"}}]}
                return new PrayerCalculationRule(null, 'weekly', 15, '04:30', '06:45', null, 30, [
                    new PrayerCalculationOverrideRule(
                        'ramadan',
                        new PrayerCalculationRule(null, 'daily', 1, null, null, 15, null, null)
                    ),
                ]);
            case 'dhuhr':
                // {"static":"12:30","overrides":[{"condition":"daylightSavingsTime",
                //  "time":{"static":"13:30"}}]}
                return new PrayerCalculationRule('12:30', null, null, null, null, null, null, [
                    new PrayerCalculationOverrideRule(
                        'daylightSavingsTime',
                        new PrayerCalculationRule('13:30')
                    ),
                ]);
            case 'maghrib':
                // {"change":"daily","roundMinutes":"1","afterAthanMinutes":10,"overrides":[...]}
                return new PrayerCalculationRule(null, 'daily', 1, null, null, 10, null, [
                    new PrayerCalculationOverrideRule(
                        'ramadan',
                        new PrayerCalculationRule(null, 'daily', 1, null, null, 10, null, null)
                    ),
                ]);
            case 'isha':
                // {"change":"daily","roundMinutes":"1","earliest":"19:45","latest":"23:30",
                //  "afterAthanMinutes":10,"overrides":[...]}
                return new PrayerCalculationRule(null, 'daily', 1, '19:45', '23:30', 10, null, [
                    new PrayerCalculationOverrideRule(
                        'ramadan',
                        new PrayerCalculationRule(null, 'daily', 1, null, null, 15, null, null)
                    ),
                ]);
            case 'asr':
                // {"change":"weekly","roundMinutes":"15","afterAthanMinutes":0}
                return new PrayerCalculationRule(null, 'weekly', 15, null, null, 0, null, null);
        }

        throw new \InvalidArgumentException("No rule configured for prayer: {$prayerName}");
    }

    /**
     * Calculate the Asr Athan for a day using the API configuration:
     * ISNA calculation method, middleOfTheNight high-latitude adjustment,
     * Bothell coordinates, elevation 0.
     *
     * @param string $school PrayerTimes::SCHOOL_STANDARD or PrayerTimes::SCHOOL_HANAFI
     * @param string $dayKey Day in Y-m-d format
     */
    private static function calculateAsrAthan(string $school, string $dayKey): DateTime
    {
        static $cache = [];

        $cacheKey = $school . $dayKey;
        if (isset($cache[$cacheKey])) {
            return clone $cache[$cacheKey];
        }

        $calculator = new PrayerTimes(Method::METHOD_ISNA, $school);
        $times = $calculator->getTimes(
            new DateTime($dayKey . ' 00:00:00', new DateTimeZone(self::TIMEZONE)),
            self::LATITUDE,
            self::LONGITUDE,
            0,
            PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
            null,
            PrayerTimes::TIME_FORMAT_24H
        );

        $athan = new DateTime(
            $dayKey . ' ' . $times[PrayerTimes::ASR],
            new DateTimeZone(self::TIMEZONE)
        );
        $cache[$cacheKey] = $athan;

        return clone $athan;
    }

    /**
     * Assert computed Iqama times match the production CSV column for every day
     *
     * @param string $prayerName Prayer under test
     * @param int $iqamaColumn CSV column index of the expected Iqama time
     * @param array<int, array> $rows Rows of one of the CSV fixtures
     * @param string $changeLabel Change description used in failure messages (e.g. "fall")
     * @param string|null $endPrayerName End prayer for beforeEndMinutes rules
     */
    private static function assertIqamaMatchesProductionCsv(
        string $prayerName,
        int $iqamaColumn,
        array $rows,
        string $changeLabel,
        ?string $endPrayerName = null
    ): void {
        $computed = self::calculateIqamaTimes($prayerName, $rows, $endPrayerName);

        foreach ($rows as $row) {
            $date = DateTime::createFromFormat('!m/d/y', $row[0], new DateTimeZone(self::TIMEZONE));
            $dayKey = $date->format('Y-m-d');
            $expected = $row[$iqamaColumn];

            self::assertArrayHasKey($dayKey, $computed, "Missing computed Iqama for {$dayKey}");
            self::assertSame(
                $expected,
                $computed[$dayKey],
                "{$prayerName} Iqama on {$dayKey} (around the {$changeLabel} DST change) does not match production"
            );
        }
    }

    /**
     * Calculate Asr Iqama times across the given CSV range from the calculated
     * Hanafi Asr Athan (asrIqamaCalculationMethod: "hanafi").
     *
     * The CSV ranges start mid-week, while production generated them with complete
     * weekly batches. The first batch is therefore extended back to the previous
     * Friday with calculated Athan times so the weekly winner matches production
     * (the CSV has no expected values for those days).
     *
     * @param array<int, array> $rows Rows of one of the CSV fixtures
     * @return array<string, string> Iqama times keyed by Y-m-d, formatted like the CSV (g:i A)
     */
    private static function calculateAsrIqamaTimes(array $rows): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);

        $firstDay = DateTime::createFromFormat('!m/d/y', $rows[0][0], $tz);
        $lastDay = DateTime::createFromFormat('!m/d/y', $rows[count($rows) - 1][0], $tz);

        $start = clone $firstDay;
        if ((int)$start->format('w') !== 5) {
            $start->modify('last friday');
        }

        $daysData = [];
        $current = $start;
        $end = (clone $lastDay)->modify('+1 day');
        while ($current < $end) {
            $dayKey = $current->format('Y-m-d');
            $daysData[$dayKey] = [
                'date' => clone $current,
                'athan' => [
                    'asr' => self::calculateAsrAthan(PrayerTimes::SCHOOL_HANAFI, $dayKey),
                ],
            ];
            $current->modify('+1 day');
        }

        $results = [];
        foreach (self::weekBatches($daysData) as $batch) {
            $results += IqamaCalculator::calculateIqama($batch, 'asr', self::ruleFor('asr'));
        }

        ksort($results);

        $formatted = [];
        foreach ($results as $dayKey => $iqama) {
            $formatted[$dayKey] = $iqama->format('g:i A');
        }

        return $formatted;
    }

    /**
     * Assert the calculated standard Asr Athan matches the CSV's asr_athan column
     * for every day. This validates the PrayerTimes engine (ISNA,
     * middleOfTheNight, Bothell coordinates) against production, including the
     * one-hour wall-clock shift across the DST change, and is what makes the
     * Hanafi Athan times used for the Asr Iqama trustworthy.
     *
     * @param array<int, array> $rows Rows of one of the CSV fixtures
     * @param string $changeLabel Change description used in failure messages (e.g. "fall")
     */
    private static function assertStandardAsrAthanMatchesProductionCsv(array $rows, string $changeLabel): void
    {
        foreach ($rows as $row) {
            $dayKey = DateTime::createFromFormat('!m/d/y', $row[0], new DateTimeZone(self::TIMEZONE))
                ->format('Y-m-d');

            $computed = self::calculateAsrAthan(PrayerTimes::SCHOOL_STANDARD, $dayKey)
                ->format('g:i A');

            self::assertSame(
                $row[6],
                $computed,
                "Standard Asr Athan on {$dayKey} (around the {$changeLabel} DST change) does not match production"
            );
        }
    }

    /**
     * Sanity check: the fall fixture actually spans the fall DST transition
     *
     * DST ends on Nov 1, 2026 at 2:00 AM. Midnight dates are still DST on the
     * transition day, while all Athan times on Nov 1 (after 2:00 AM) are standard.
     */
    public function testFixtureSpansFallDstTransition()
    {
        $daysData = self::allDaysData(self::FALL_CSV_ROWS);

        $this->assertSame('1', $daysData['2026-10-31']['date']->format('I'), 'Oct 31 should be DST');
        $this->assertSame('1', $daysData['2026-11-01']['date']->format('I'), 'Nov 1 midnight is still DST');
        $this->assertSame('0', $daysData['2026-11-02']['date']->format('I'), 'Nov 2 should be standard time');
        $this->assertSame(
            '0',
            $daysData['2026-11-01']['athan']['dhuhr']->format('I'),
            'Nov 1 Dhuhr Athan (11:52 AM) is after the 2 AM fall-back, so it is standard time'
        );
        $this->assertSame(
            '1',
            $daysData['2026-10-31']['athan']['dhuhr']->format('I'),
            'Oct 31 Dhuhr Athan is DST'
        );
    }

    /**
     * Sanity check: the spring fixture actually spans the spring DST transition
     *
     * DST starts on Mar 14, 2027 at 2:00 AM. Midnight dates are still standard on
     * the transition day, while all Athan times on Mar 14 (after 2:00 AM) are DST.
     */
    public function testFixtureSpansSpringDstTransition()
    {
        $daysData = self::allDaysData(self::SPRING_CSV_ROWS);

        $this->assertSame('0', $daysData['2027-03-13']['date']->format('I'), 'Mar 13 should be standard time');
        $this->assertSame('0', $daysData['2027-03-14']['date']->format('I'), 'Mar 14 midnight is still standard time');
        $this->assertSame('1', $daysData['2027-03-15']['date']->format('I'), 'Mar 15 should be DST');
        $this->assertSame(
            '1',
            $daysData['2027-03-14']['athan']['dhuhr']->format('I'),
            'Mar 14 Dhuhr Athan (1:18 PM) is after the 2 AM spring-forward, so it is DST'
        );
        $this->assertSame(
            '0',
            $daysData['2027-03-13']['athan']['dhuhr']->format('I'),
            'Mar 13 Dhuhr Athan is standard time'
        );
    }

    /**
     * Dhuhr Iqama uses the DST override (1:30 PM) before the fall change and the
     * base static time (12:30 PM) from the transition day onward. The override is
     * resolved at the Athan time, so Nov 1 (Athan 11:52 AM, after the 2 AM
     * fall-back) already uses standard time - matching production.
     */
    public function testDhuhrIqamaMatchesProductionCsvAcrossFallChange()
    {
        self::assertIqamaMatchesProductionCsv('dhuhr', 5, self::FALL_CSV_ROWS, 'fall');
    }

    /**
     * Fajr Iqama (weekly, 30 min before sunrise) across the fall change: the week
     * spanning the transition renders 6:45 AM on DST days (clamped by the 6:45
     * latest constraint) and 6:15 AM on standard days - matching production.
     */
    public function testFajrIqamaMatchesProductionCsvAcrossFallChange()
    {
        self::assertIqamaMatchesProductionCsv('fajr', 2, self::FALL_CSV_ROWS, 'fall', 'sunrise');
    }

    /**
     * Maghrib Iqama (daily, Athan + 10 min) across the fall change: wall-clock
     * times shift one hour back with the clocks - matching production.
     */
    public function testMaghribIqamaMatchesProductionCsvAcrossFallChange()
    {
        self::assertIqamaMatchesProductionCsv('maghrib', 9, self::FALL_CSV_ROWS, 'fall');
    }

    /**
     * Isha Iqama (daily, Athan + 10 min, clamped to earliest 7:45 PM) across the
     * fall change - matching production.
     */
    public function testIshaIqamaMatchesProductionCsvAcrossFallChange()
    {
        self::assertIqamaMatchesProductionCsv('isha', 11, self::FALL_CSV_ROWS, 'fall');
    }

    /**
     * Dhuhr Iqama uses the base static time (12:30 PM) before the spring change
     * and the DST override (1:30 PM) from the transition day onward. The override
     * is resolved at the Athan time, so Mar 14 (Athan 1:18 PM, after the 2 AM
     * spring-forward) already uses DST time - matching production.
     */
    public function testDhuhrIqamaMatchesProductionCsvAcrossSpringChange()
    {
        self::assertIqamaMatchesProductionCsv('dhuhr', 5, self::SPRING_CSV_ROWS, 'spring');
    }

    /**
     * Fajr Iqama (weekly, 30 min before sunrise) across the spring change: the
     * week spanning the transition renders 5:45 AM on standard days and 6:45 AM
     * on DST days (the same absolute instant) - matching production.
     */
    public function testFajrIqamaMatchesProductionCsvAcrossSpringChange()
    {
        self::assertIqamaMatchesProductionCsv('fajr', 2, self::SPRING_CSV_ROWS, 'spring', 'sunrise');
    }

    /**
     * Maghrib Iqama (daily, Athan + 10 min) across the spring change: wall-clock
     * times shift one hour forward with the clocks - matching production.
     */
    public function testMaghribIqamaMatchesProductionCsvAcrossSpringChange()
    {
        self::assertIqamaMatchesProductionCsv('maghrib', 9, self::SPRING_CSV_ROWS, 'spring');
    }

    /**
     * Isha Iqama (daily, Athan + 10 min, clamped to earliest 7:45 PM) across the
     * spring change - matching production.
     */
    public function testIshaIqamaMatchesProductionCsvAcrossSpringChange()
    {
        self::assertIqamaMatchesProductionCsv('isha', 11, self::SPRING_CSV_ROWS, 'spring');
    }

    /**
     * Focused check of the weekly Fajr behavior within the single week that spans
     * the fall change (Fri Oct 30 - Thu Nov 5): the Iqama is the same absolute
     * instant on both sides of the boundary, so DST days get 6:45 AM (clamped by
     * the latest constraint) while standard days get 6:15 AM.
     */
    public function testFajrWeeklyIqamaChangesAtDstBoundaryWithinSameWeekFallChange()
    {
        $batch = self::daysDataFromRows(array_slice(self::FALL_CSV_ROWS, 1, 7)); // 2026-10-30 .. 2026-11-05

        $this->assertCount(7, $batch);
        $this->assertSame('5', $batch['2026-10-30']['date']->format('w'), 'Batch should start on Friday');
        $this->assertSame('4', $batch['2026-11-05']['date']->format('w'), 'Batch should end on Thursday');

        $results = IqamaCalculator::calculateIqama(
            $batch,
            'fajr',
            self::ruleFor('fajr'),
            'sunrise'
        );

        $this->assertSame('06:45', $results['2026-10-30']->format('H:i'), 'Oct 30 (DST)');
        $this->assertSame('06:45', $results['2026-10-31']->format('H:i'), 'Oct 31 (DST)');
        $this->assertSame('06:15', $results['2026-11-01']->format('H:i'), 'Nov 1 (fall-back day, standard)');
        $this->assertSame('06:15', $results['2026-11-02']->format('H:i'), 'Nov 2 (standard)');
        $this->assertSame('06:15', $results['2026-11-05']->format('H:i'), 'Nov 5 (standard)');
    }

    /**
     * Focused check of the weekly Fajr behavior within the single week that spans
     * the spring change (Fri Mar 12 - Thu Mar 18): the Iqama is the same absolute
     * instant on both sides of the boundary, so standard days get 5:45 AM while
     * DST days get 6:45 AM.
     */
    public function testFajrWeeklyIqamaChangesAtDstBoundaryWithinSameWeekSpringChange()
    {
        $batch = self::daysDataFromRows(array_slice(self::SPRING_CSV_ROWS, 3, 7)); // 2027-03-12 .. 2027-03-18

        $this->assertCount(7, $batch);
        $this->assertSame('5', $batch['2027-03-12']['date']->format('w'), 'Batch should start on Friday');
        $this->assertSame('4', $batch['2027-03-18']['date']->format('w'), 'Batch should end on Thursday');

        $results = IqamaCalculator::calculateIqama(
            $batch,
            'fajr',
            self::ruleFor('fajr'),
            'sunrise'
        );

        $this->assertSame('05:45', $results['2027-03-12']->format('H:i'), 'Mar 12 (standard)');
        $this->assertSame('05:45', $results['2027-03-13']->format('H:i'), 'Mar 13 (standard)');
        $this->assertSame('06:45', $results['2027-03-14']->format('H:i'), 'Mar 14 (spring-forward day, DST)');
        $this->assertSame('06:45', $results['2027-03-15']->format('H:i'), 'Mar 15 (DST)');
        $this->assertSame('06:45', $results['2027-03-18']->format('H:i'), 'Mar 18 (DST)');
    }

    /**
     * Focused check of the Dhuhr DST override on the fall change day itself:
     * Oct 31 (DST) -> 1:30 PM, Nov 1 (transition day, Athan after fall-back) ->
     * 12:30 PM, Nov 2 (standard) -> 12:30 PM.
     */
    public function testDhuhrIqamaUsesStandardTimeOnFallChangeDay()
    {
        $batch = self::daysDataFromRows(array_slice(self::FALL_CSV_ROWS, 2, 3)); // 2026-10-31, 2026-11-01, 2026-11-02

        $results = IqamaCalculator::calculateIqama(
            $batch,
            'dhuhr',
            self::ruleFor('dhuhr')
        );

        $this->assertSame('13:30', $results['2026-10-31']->format('H:i'), 'Oct 31 (DST) should use the DST override');
        $this->assertSame('12:30', $results['2026-11-01']->format('H:i'), 'Nov 1 Athan is after the fall-back, should use standard time');
        $this->assertSame('12:30', $results['2026-11-02']->format('H:i'), 'Nov 2 (standard) should use standard time');
    }

    /**
     * Focused check of the Dhuhr DST override on the spring change day itself:
     * Mar 13 (standard) -> 12:30 PM, Mar 14 (transition day, Athan after
     * spring-forward) -> 1:30 PM, Mar 15 (DST) -> 1:30 PM.
     */
    public function testDhuhrIqamaUsesDstTimeOnSpringChangeDay()
    {
        $batch = self::daysDataFromRows(array_slice(self::SPRING_CSV_ROWS, 4, 3)); // 2027-03-13, 2027-03-14, 2027-03-15

        $results = IqamaCalculator::calculateIqama(
            $batch,
            'dhuhr',
            self::ruleFor('dhuhr')
        );

        $this->assertSame('12:30', $results['2027-03-13']->format('H:i'), 'Mar 13 (standard) should use standard time');
        $this->assertSame('13:30', $results['2027-03-14']->format('H:i'), 'Mar 14 Athan is after the spring-forward, should use the DST override');
        $this->assertSame('13:30', $results['2027-03-15']->format('H:i'), 'Mar 15 (DST) should use the DST override');
    }

    /**
     * The calculated standard Asr Athan must match the CSV's asr_athan column
     * exactly for the fall change range.
     */
    public function testStandardAsrAthanMatchesProductionCsvAcrossFallChange()
    {
        self::assertStandardAsrAthanMatchesProductionCsv(self::FALL_CSV_ROWS, 'fall');
    }

    /**
     * The calculated standard Asr Athan must match the CSV's asr_athan column
     * exactly for the spring change range.
     */
    public function testStandardAsrAthanMatchesProductionCsvAcrossSpringChange()
    {
        self::assertStandardAsrAthanMatchesProductionCsv(self::SPRING_CSV_ROWS, 'spring');
    }

    /**
     * Asr Iqama (weekly, round 15 min, Athan + 0) driven by the calculated Hanafi
     * Asr Athan across the fall change: 4:30 PM on Oct 29 (winner of the full week
     * starting Oct 23), 4:15 PM on DST days vs 3:15 PM on standard days within the
     * week spanning the transition (the same absolute instant), then 3:00 PM after
     * the next weekly change - all matching production.
     */
    public function testAsrIqamaMatchesProductionCsvAcrossFallChange()
    {
        $computed = self::calculateAsrIqamaTimes(self::FALL_CSV_ROWS);

        foreach (self::FALL_CSV_ROWS as $row) {
            $dayKey = DateTime::createFromFormat('!m/d/y', $row[0], new DateTimeZone(self::TIMEZONE))
                ->format('Y-m-d');

            self::assertArrayHasKey($dayKey, $computed, "Missing computed Asr Iqama for {$dayKey}");
            self::assertSame(
                $row[7],
                $computed[$dayKey],
                "Asr Iqama on {$dayKey} (around the fall DST change) does not match production"
            );
        }
    }

    /**
     * Asr Iqama (weekly, round 15 min, Athan + 0) driven by the calculated Hanafi
     * Asr Athan across the spring change: 4:30 PM while the weekly winner is on
     * standard days, then 4:30 PM vs 5:30 PM within the week spanning the
     * transition (the same absolute instant), then 5:45 PM after the next weekly
     * change - all matching production.
     */
    public function testAsrIqamaMatchesProductionCsvAcrossSpringChange()
    {
        $computed = self::calculateAsrIqamaTimes(self::SPRING_CSV_ROWS);

        foreach (self::SPRING_CSV_ROWS as $row) {
            $dayKey = DateTime::createFromFormat('!m/d/y', $row[0], new DateTimeZone(self::TIMEZONE))
                ->format('Y-m-d');

            self::assertArrayHasKey($dayKey, $computed, "Missing computed Asr Iqama for {$dayKey}");
            self::assertSame(
                $row[7],
                $computed[$dayKey],
                "Asr Iqama on {$dayKey} (around the spring DST change) does not match production"
            );
        }
    }

    /**
     * Focused check of the weekly Asr behavior within the single week that spans
     * the fall change (Fri Oct 30 - Thu Nov 5): the Iqama is the same absolute
     * instant on both sides of the boundary, so DST days get 4:15 PM while
     * standard days get 3:15 PM.
     */
    public function testAsrWeeklyIqamaChangesAtDstBoundaryWithinSameWeekFallChange()
    {
        $results = IqamaCalculator::calculateIqama(
            self::hanafiAsrBatch('2026-10-30', '2026-11-05'),
            'asr',
            self::ruleFor('asr')
        );

        $this->assertSame('16:15', $results['2026-10-30']->format('H:i'), 'Oct 30 (DST)');
        $this->assertSame('16:15', $results['2026-10-31']->format('H:i'), 'Oct 31 (DST)');
        $this->assertSame('15:15', $results['2026-11-01']->format('H:i'), 'Nov 1 (fall-back day, standard)');
        $this->assertSame('15:15', $results['2026-11-02']->format('H:i'), 'Nov 2 (standard)');
        $this->assertSame('15:15', $results['2026-11-05']->format('H:i'), 'Nov 5 (standard)');
    }

    /**
     * Focused check of the weekly Asr behavior within the single week that spans
     * the spring change (Fri Mar 12 - Thu Mar 18): the Iqama is the same absolute
     * instant on both sides of the boundary, so standard days get 4:30 PM while
     * DST days get 5:30 PM.
     */
    public function testAsrWeeklyIqamaChangesAtDstBoundaryWithinSameWeekSpringChange()
    {
        $batch = self::hanafiAsrBatch('2027-03-12', '2027-03-18');

        $this->assertSame('5', $batch['2027-03-12']['date']->format('w'), 'Batch should start on Friday');
        $this->assertSame('4', $batch['2027-03-18']['date']->format('w'), 'Batch should end on Thursday');

        $results = IqamaCalculator::calculateIqama(
            $batch,
            'asr',
            self::ruleFor('asr')
        );

        $this->assertSame('16:30', $results['2027-03-12']->format('H:i'), 'Mar 12 (standard)');
        $this->assertSame('16:30', $results['2027-03-13']->format('H:i'), 'Mar 13 (standard)');
        $this->assertSame('17:30', $results['2027-03-14']->format('H:i'), 'Mar 14 (spring-forward day, DST)');
        $this->assertSame('17:30', $results['2027-03-15']->format('H:i'), 'Mar 15 (DST)');
        $this->assertSame('17:30', $results['2027-03-18']->format('H:i'), 'Mar 18 (DST)');
    }

    /**
     * Build a weekly batch of days_data with calculated Hanafi Asr Athan for the
     * given inclusive date range (used by the focused Asr boundary tests).
     *
     * @param string $startDay Start day in Y-m-d format (inclusive)
     * @param string $endDay End day in Y-m-d format (inclusive)
     * @return array<string, array>
     */
    private static function hanafiAsrBatch(string $startDay, string $endDay): array
    {
        $tz = new DateTimeZone(self::TIMEZONE);

        $batch = [];
        $current = new DateTime($startDay . ' 00:00:00', $tz);
        $end = (new DateTime($endDay . ' 00:00:00', $tz))->modify('+1 day');
        while ($current < $end) {
            $dayKey = $current->format('Y-m-d');
            $batch[$dayKey] = [
                'date' => clone $current,
                'athan' => [
                    'asr' => self::calculateAsrAthan(PrayerTimes::SCHOOL_HANAFI, $dayKey),
                ],
            ];
            $current->modify('+1 day');
        }

        return $batch;
    }
}
