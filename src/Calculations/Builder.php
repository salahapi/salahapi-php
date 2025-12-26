<?php

namespace SalahAPI\Calculations;

use DateTime;
use DateTimeZone;
use DateInterval;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;

/**
 * Prayer Times Builder
 * 
 * Builds prayer times based on SalahAPI data and date range.
 * Uses calculation methods from PrayerTimes and Iqama calculation logic.
 */
class Builder
{
    private PrayerTimes $prayerTimes;
    private Location $location;
    private CalculationMethod $calculationMethod;
    private int $elevation;

    /**
     * Constructor
     * 
     * @param Location $location Location configuration
     * @param CalculationMethod $calculationMethod Calculation method configuration
     * @param int $elevation Elevation in meters (default: 0)
     */
    public function __construct(
        Location $location,
        CalculationMethod $calculationMethod,
        int $elevation = 0
    ) {
        $this->location = $location;
        $this->calculationMethod = $calculationMethod;
        $this->elevation = $elevation;
        
        // Initialize the prayer times calculator
        $this->prayerTimes = new PrayerTimes(
            $calculationMethod->name,
            $calculationMethod->asrCalculationMethod ?? PrayerTimes::SCHOOL_STANDARD
        );
    }

    /**
     * Build prayer times for a date range
     * 
     * @param DateTime|string $startDate Start date
     * @param DateTime|string $endDate End date
     * @return array Array of prayer time data
     */
    public function build($startDate, $endDate): array
    {
        // Convert string dates to DateTime if needed
        $dtz = new DateTimeZone($this->location->timezone);
        
        if (is_string($startDate)) {
            $startDate = new DateTime($startDate, $dtz);
        } else {
            $startDate = clone $startDate;
            $startDate->setTimezone($dtz);
        }
        
        if (is_string($endDate)) {
            $endDate = new DateTime($endDate, $dtz);
        } else {
            $endDate = clone $endDate;
            $endDate->setTimezone($dtz);
        }
        
        // Calculate number of days
        $interval = $startDate->diff($endDate);
        $daysToGenerate = (int)$interval->days + 1; // Include end date
        
        // Process in weekly batches if using weekly frequency
        $iqamaRules = $this->calculationMethod->iqamaCalculationRules;
        $isWeekly = ($iqamaRules?->changeOn ?? null) !== null;
        
        // Collect all prayer times first
        $allDaysData = [];
        $currentDate = clone $startDate;
        
        for ($i = 0; $i < $daysToGenerate; $i++) {
            // Get prayer times for this day
            $times = $this->prayerTimes->getTimes(
                $currentDate,
                $this->location->latitude,
                $this->location->longitude,
                $this->elevation,
                $this->calculationMethod->highLatitudeAdjustment ?? PrayerTimes::LATITUDE_ADJUSTMENT_METHOD_MOTN,
                null,
                PrayerTimes::TIME_FORMAT_24H
            );
            
            // Store day data with DateTime objects
            $datePrefix = $currentDate->format('Y-m-d') . ' ';
            $allDaysData[$i] = [
                'date' => clone $currentDate,
                'athan' => [
                    'fajr' => new DateTime($datePrefix . $times[PrayerTimes::FAJR], $dtz),
                    'sunrise' => new DateTime($datePrefix . $times[PrayerTimes::SUNRISE], $dtz),
                    'dhuhr' => new DateTime($datePrefix . $times[PrayerTimes::ZHUHR], $dtz),
                    'asr' => new DateTime($datePrefix . $times[PrayerTimes::ASR], $dtz),
                    'maghrib' => new DateTime($datePrefix . $times[PrayerTimes::MAGHRIB], $dtz),
                    'isha' => new DateTime($datePrefix . $times[PrayerTimes::ISHA], $dtz),
                ]
            ];
            
            $currentDate->modify('+1 day');
        }
        
        // Now process in weekly batches or all at once
        $csvData = [];
        $csvData[] = [
            'day', 'fajr_athan', 'fajr_iqama', 'sunrise',
            'dhuhr_athan', 'dhuhr_iqama', 'asr_athan', 'asr_iqama',
            'maghrib_athan', 'maghrib_iqama', 'isha_athan', 'isha_iqama'
        ];
        
        if ($isWeekly) {
            $csvData = array_merge($csvData, $this->processWeekly($allDaysData, $dtz));
        } else {
            $csvData = array_merge($csvData, $this->processDaily($allDaysData, $dtz));
        }
        
        return $csvData;
    }

    /**
     * Process days in weekly batches
     * 
     * @param array $allDaysData All day data
     * @param DateTimeZone $dtz Timezone
     * @return array CSV rows
     */
    private function processWeekly(array $allDaysData, DateTimeZone $dtz): array
    {
        $csvRows = [];
        $currentWeekStart = null;
        $weekDaysData = [];
        $processedDays = 0;
        $totalDays = count($allDaysData);
        
        // Determine the day of week when iqama times change
        $changeOnDay = $this->calculationMethod->iqamaCalculationRules?->changeOn ?? 'Friday';
        $changeOnDayNumber = $this->getDayOfWeekNumber($changeOnDay);
        
        foreach ($allDaysData as $dayIndex => $dayData) {
            $currentDate = $dayData['date'];
            $currentDayNumber = (int)$currentDate->format('w');
            $isChangeDay = $currentDayNumber == $changeOnDayNumber;
            
            // Start a new week on the change day
            if ($isChangeDay || $currentWeekStart === null) {
                if ($currentWeekStart !== null && !empty($weekDaysData)) {
                    // Process the previous week
                    $csvRows = array_merge($csvRows, $this->calculateWeekIqama($weekDaysData, $dtz));
                }
                $currentWeekStart = clone $currentDate;
                $weekDaysData = [];
            }
            
            // Add this day to the week
            $weekDaysData[$dayIndex] = $dayData;
            
            // Check if this is the last day or end of week (day before change day)
            $dayBeforeChange = ($changeOnDayNumber - 1 + 7) % 7;
            $isDayBeforeChange = $currentDayNumber == $dayBeforeChange;
            $isLastDay = ($processedDays + 1) >= $totalDays;
            
            if ($isDayBeforeChange || $isLastDay) {
                // Process this week
                $csvRows = array_merge($csvRows, $this->calculateWeekIqama($weekDaysData, $dtz));
                $weekDaysData = [];
                $currentWeekStart = null;
            }
            
            $processedDays++;
        }
        
        // Process any remaining days
        if (!empty($weekDaysData)) {
            $csvRows = array_merge($csvRows, $this->calculateWeekIqama($weekDaysData, $dtz));
        }
        
        return $csvRows;
    }

    /**
     * Process all days individually (daily frequency)
     * 
     * @param array $allDaysData All day data
     * @param DateTimeZone $dtz Timezone
     * @return array CSV rows
     */
    private function processDaily(array $allDaysData, DateTimeZone $dtz): array
    {
        return $this->calculateWeekIqama($allDaysData, $dtz);
    }

    /**
     * Calculate Iqama times for a week (or batch) of days
     * 
     * @param array $weekDaysData Days data for the week
     * @param DateTimeZone $dtz Timezone
     * @return array CSV rows
     */
    private function calculateWeekIqama(array $weekDaysData, DateTimeZone $dtz): array
    {
        if (empty($weekDaysData)) {
            return [];
        }
        
        $iqamaRules = $this->calculationMethod->iqamaCalculationRules;
        
        // Calculate Iqama times for each prayer using the generic calculateIqama method
        $fajrIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'fajr',
            $iqamaRules?->fajr,
            'sunrise'  // End prayer name for beforeEndMinutes calculation
        );
        
        $dhuhrIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'dhuhr',
            $iqamaRules?->dhuhr
        );
        
        $asrIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'asr',
            $iqamaRules?->asr
        );
        
        $maghribIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'maghrib',
            $iqamaRules?->maghrib
        );
        
        $ishaIqamaTimes = IqamaCalculator::calculateIqama(
            $weekDaysData,
            'isha',
            $iqamaRules?->isha
        );
        
        // Build CSV rows
        $csvRows = [];
        
        foreach ($weekDaysData as $dayIndex => $dayData) {
            $date = $dayData['date'];
            $athan = $dayData['athan'];
            
            $csvRows[] = [
                $date->format('Y-m-d'),
                $athan['fajr']->format('H:i'),
                isset($fajrIqamaTimes[$dayIndex]) ? $fajrIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['sunrise']->format('H:i'),
                $athan['dhuhr']->format('H:i'),
                isset($dhuhrIqamaTimes[$dayIndex]) ? $dhuhrIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['asr']->format('H:i'),
                isset($asrIqamaTimes[$dayIndex]) ? $asrIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['maghrib']->format('H:i'),
                isset($maghribIqamaTimes[$dayIndex]) ? $maghribIqamaTimes[$dayIndex]->format('H:i') : '',
                $athan['isha']->format('H:i'),
                isset($ishaIqamaTimes[$dayIndex]) ? $ishaIqamaTimes[$dayIndex]->format('H:i') : '',
            ];
        }
        
        return $csvRows;
    }

    /**
     * Build and return as CSV string
     * 
     * @param DateTime|string $startDate Start date
     * @param DateTime|string $endDate End date
     * @return string CSV content
     */
    public function buildCsv($startDate, $endDate): string
    {
        $data = $this->build($startDate, $endDate);
        
        $csvContent = '';
        foreach ($data as $row) {
            $csvContent .= implode(',', $row) . "\n";
        }
        
        return $csvContent;
    }

    /**
     * Build and return as array of associative arrays
     * 
     * @param DateTime|string $startDate Start date
     * @param DateTime|string $endDate End date
     * @return array Array of associative arrays
     */
    public function buildAssociative($startDate, $endDate): array
    {
        $data = $this->build($startDate, $endDate);
        
        // Remove header row
        $header = array_shift($data);
        
        // Convert to associative arrays
        $result = [];
        foreach ($data as $row) {
            $assoc = [];
            foreach ($header as $index => $key) {
                $assoc[$key] = $row[$index] ?? null;
            }
            $result[] = $assoc;
        }
        
        return $result;
    }

    /**
     * Get day of week number from day name
     * 
     * @param string $dayName Day name (e.g., 'Monday', 'Friday')
     * @return int Day number (0 = Sunday, 1 = Monday, ..., 6 = Saturday)
     */
    private function getDayOfWeekNumber(string $dayName): int
    {
        $days = [
            'Sunday' => 0,
            'Monday' => 1,
            'Tuesday' => 2,
            'Wednesday' => 3,
            'Thursday' => 4,
            'Friday' => 5,
            'Saturday' => 6,
        ];
        
        return $days[$dayName] ?? 5; // Default to Friday
    }
}
