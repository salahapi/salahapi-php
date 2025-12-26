<?php

namespace SalahAPI\Calculations;

use DateTime;
use SalahAPI\PrayerCalculationRule;

/**
 * Calculator for Iqama (congregation) prayer times
 */
class IqamaCalculator
{
    /**
     * Calculate Iqama times for a specific prayer using a generic rule
     * 
     * @param array $daysData Array of day data with athan times
     * @param string $prayerName Name of the prayer ('fajr', 'dhuhr', 'asr', 'maghrib', 'isha')
     * @param PrayerCalculationRule|null $rule The calculation rule to apply
     * @param string|null $endPrayerName Optional name of the prayer that marks the end of the timeframe (e.g., 'sunrise' for Fajr)
     * @return array Array of DateTime objects indexed by day index
     */
    public static function calculateIqama(
        array $daysData,
        string $prayerName,
        ?PrayerCalculationRule $rule = null,
        ?string $endPrayerName = null
    ): array {
        // If no rule is provided, return empty array
        if ($rule === null) {
            return [];
        }
        
        $results = [];
        
        // If static time is specified, evaluate overrides per-day
        if ($rule->static !== null) {
            foreach ($daysData as $dayIndex => $dayData) {
                $dayDate = $dayData['date'];
                // Resolve effective rule for this specific day (handles DST overrides per-day)
                $effectiveRule = self::getEffectiveRule($rule, $dayDate);
                $staticTime = TimeHelpers::parseTimeString($dayDate, $effectiveRule->static);
                $results[$dayIndex] = $staticTime;
            }
            return $results;
        }
        
        // Determine if this is a weekly calculation
        $isWeekly = ($rule->change === 'weekly');
        
        // Set default values
        $roundMinutes = $rule->roundMinutes ?? 1;
        $afterAthanMinutes = $rule->afterAthanMinutes ?? 0;
        $beforeEndMinutes = $rule->beforeEndMinutes ?? 0;
        $earliestTime = $rule->earliest ?? '00:00';
        $latestTime = $rule->latest ?? '23:59';
        
        // Normalize all times
        $normalizedDaysData = TimeHelpers::normalizeTimesForDst($daysData);
        
        // Find latest Athan and/or end time for weekly calculation
        $latestAthan = null;
        $latestEndTime = null;
        
        if ($isWeekly) {
            foreach ($normalizedDaysData as $dayData) {
                // Get the athan time for this prayer
                if (isset($dayData['athan'][$prayerName])) {
                    $dayAthan = $dayData['athan'][$prayerName];
                    if ($latestAthan === null || TimeHelpers::timeToMinutes($dayAthan) > TimeHelpers::timeToMinutes($latestAthan)) {
                        $latestAthan = clone $dayAthan;
                    }
                }
                
                // Get the end time if specified (e.g., sunrise for Fajr)
                if ($endPrayerName !== null && isset($dayData['athan'][$endPrayerName])) {
                    $dayEndTime = $dayData['athan'][$endPrayerName];
                    if ($latestEndTime === null || TimeHelpers::timeToMinutes($dayEndTime) > TimeHelpers::timeToMinutes($latestEndTime)) {
                        $latestEndTime = clone $dayEndTime;
                    }
                }
            }
            
            // Apply rounding to the latest times
            if ($latestAthan) {
                $latestAthan = TimeHelpers::roundUp($latestAthan, $roundMinutes);
            }
            if ($latestEndTime) {
                $latestEndTime = TimeHelpers::roundDown($latestEndTime, $roundMinutes);
            }
        }
        
        // Process each day
        foreach ($normalizedDaysData as $dayIndex => $dayData) {
            $dayDate = $dayData['date'];
            $dayAthan = $dayData['athan'][$prayerName] ?? null;
            
            if ($dayAthan === null) {
                continue; // Skip if athan time is not available
            }
            
            // Determine iqama time based on rule
            if ($isWeekly) {
                // Weekly calculation: use the latest athan time across the week
                if ($beforeEndMinutes > 0 && $latestEndTime !== null) {
                    // Calculate based on time before end (e.g., before sunrise for Fajr)
                    $dayIqama = clone $latestEndTime;
                    $dayIqama->modify("-{$beforeEndMinutes} minutes");
                } else {
                    // Calculate based on time after athan
                    $dayIqama = clone $latestAthan;
                    $dayIqama->modify("+{$afterAthanMinutes} minutes");
                }
                
                // Set the date to the current day's date for proper constraint comparison
                $dayIqama->setDate(
                    (int)$dayDate->format('Y'),
                    (int)$dayDate->format('m'),
                    (int)$dayDate->format('d')
                );
            } else {
                // Daily calculation
                if ($beforeEndMinutes > 0 && $endPrayerName !== null && isset($dayData['athan'][$endPrayerName])) {
                    // Calculate based on time before end
                    $dayEndTime = $dayData['athan'][$endPrayerName];
                    $dayIqama = clone $dayEndTime;
                    $dayIqama = TimeHelpers::roundDown($dayIqama, $roundMinutes);
                    $dayIqama->modify("-{$beforeEndMinutes} minutes");
                } else {
                    // Calculate based on time after athan
                    $dayIqama = clone $dayAthan;
                    $dayIqama = TimeHelpers::roundUp($dayIqama, $roundMinutes);
                    $dayIqama->modify("+{$afterAthanMinutes} minutes");
                }
            }
            
            // Denormalize the result to account for DST before applying constraints
            $dayIqama = TimeHelpers::denormalizeTimeForDst($dayIqama);
            
            // Create min and max time constraints
            $minTime = TimeHelpers::parseTimeString($dayDate, $earliestTime);
            $maxTime = TimeHelpers::parseTimeString($dayDate, $latestTime);
            
            // Apply minimum/maximum constraints
            if ($dayIqama < $minTime) {
                $dayIqama = $minTime;
            }
            
            if ($dayIqama > $maxTime) {
                $dayIqama = $maxTime;
            }
            
            $results[$dayIndex] = $dayIqama;
        }
        
        return $results;
    }

    /**
     * Get the effective rule by resolving overrides
     * 
     * Resolves overrides based on conditions (e.g., daylight savings time)
     * and returns the appropriate rule to use.
     * 
     * @param PrayerCalculationRule|null $baseRule The base rule with potential overrides
     * @param DateTime $date The date to check for override conditions
     * @return PrayerCalculationRule|null The effective rule (override or base)
     */
    private static function getEffectiveRule(?PrayerCalculationRule $baseRule, DateTime $date): ?PrayerCalculationRule
    {
        if ($baseRule === null || $baseRule->overrides === null || empty($baseRule->overrides)) {
            return $baseRule;
        }
        
        $isDst = $date->format('I') == '1';
        
        foreach ($baseRule->overrides as $override) {
            if ($override->condition === 'daylightSavingsTime' && $isDst) {
                return $override->time;
            }
            // Future: Add more conditions as needed (ramadan, dateRange, etc.)
        }
        
        return $baseRule;
    }
}

//     /**
//      * Calculate Fajr Iqama times for a collection of days
//      * 
//      * @param array $daysData Array of day data with athan times
//      * @param string $fajrRule Rule for calculating Fajr Iqama ('after_athan', 'before_sunrise', 'weekly')
//      * @param int $fajrMinutesAfter Minutes after Athan
//      * @param int $fajrMinutesBeforeShuruq Minutes before sunrise
//      * @param bool $isWeekly Whether to use weekly calculation
//      * @param int $fajrRounding Rounding interval in minutes
//      * @param string $fajrMinTime Minimum time constraint (HH:MM)
//      * @param string $fajrMaxTime Maximum time constraint (HH:MM)
//      * @return array Array of DateTime objects indexed by day index
//      */
//     public static function calculateFajrIqama(
//         array $daysData,
//         string $fajrRule,
//         int $fajrMinutesAfter,
//         int $fajrMinutesBeforeShuruq,
//         bool $isWeekly,
//         int $fajrRounding,
//         string $fajrMinTime = '00:00',
//         string $fajrMaxTime = '23:59'
//     ): array {
//         $results = [];
        
//         // Normalize all times
//         $normalizedDaysData = TimeHelpers::normalizeTimesForDst($daysData);
        
//         // Find latest Athan for weekly calculation
//         $latestFajr = null;
//         $latestSunrise = null;
        
//         if ($isWeekly) {
//             foreach ($normalizedDaysData as $dayData) {
//                 $dayFajrAthan = $dayData['athan']['fajr'];
//                 if ($latestFajr === null || TimeHelpers::timeToMinutes($dayFajrAthan) > TimeHelpers::timeToMinutes($latestFajr)) {
//                     $latestFajr = clone $dayFajrAthan;
//                 }
                
//                 if (isset($dayData['athan']['sunrise'])) {
//                     $daySunrise = $dayData['athan']['sunrise'];
//                     if ($latestSunrise === null || TimeHelpers::timeToMinutes($daySunrise) > TimeHelpers::timeToMinutes($latestSunrise)) {
//                         $latestSunrise = clone $daySunrise;
//                     }
//                 }
//             }
            
//             // Apply rounding to the latest times
//             if ($latestFajr) {
//                 $latestFajr = TimeHelpers::roundUp($latestFajr, $fajrRounding);
//             }
//             if ($latestSunrise) {
//                 $latestSunrise = TimeHelpers::roundDown($latestSunrise, $fajrRounding);
//             }
//         }
        
//         // Process each day
//         foreach ($normalizedDaysData as $dayIndex => $dayData) {
//             $dayDate = $dayData['date'];
//             $dayFajrAthan = $dayData['athan']['fajr'];
            
//             // Determine iqama time based on rule
//             if ($isWeekly) {
//                 if ($fajrRule === 'after_athan') {
//                     $dayFajrIqama = clone $latestFajr;
//                     $dayFajrIqama->modify("+{$fajrMinutesAfter} minutes");
//                 } elseif ($fajrRule === 'before_sunrise') {
//                     $dayFajrIqama = clone $latestSunrise;
//                     $dayFajrIqama->modify("-{$fajrMinutesBeforeShuruq} minutes");
//                 } else {
//                     $dayFajrIqama = clone $latestFajr;
//                     $dayFajrIqama->modify("+{$fajrMinutesAfter} minutes");
//                 }
//             } else {
//                 // Daily calculation
//                 if ($fajrRule === 'after_athan') {
//                     $dayFajrIqama = clone $dayFajrAthan;
//                     $dayFajrIqama = TimeHelpers::roundUp($dayFajrIqama, $fajrRounding);
//                     $dayFajrIqama->modify("+{$fajrMinutesAfter} minutes");
//                 } elseif ($fajrRule === 'before_sunrise' && isset($dayData['athan']['sunrise'])) {
//                     $dayFajrIqama = clone $dayData['athan']['sunrise'];
//                     $dayFajrIqama = TimeHelpers::roundDown($dayFajrIqama, $fajrRounding);
//                     $dayFajrIqama->modify("-{$fajrMinutesBeforeShuruq} minutes");
//                 } else {
//                     $dayFajrIqama = clone $dayFajrAthan;
//                     $dayFajrIqama = TimeHelpers::roundUp($dayFajrIqama, $fajrRounding);
//                     $dayFajrIqama->modify("+{$fajrMinutesAfter} minutes");
//                 }
//             }
            
//             // Denormalize the result to account for DST before applying constraints
//             $dayFajrIqama = TimeHelpers::denormalizeTimeForDst($dayFajrIqama);
            
//             // Create min and max time constraints
//             $minFajrTime = TimeHelpers::parseTimeString($dayDate, $fajrMinTime);
//             $maxFajrTime = TimeHelpers::parseTimeString($dayDate, $fajrMaxTime);
            
//             // Apply minimum/maximum constraints
//             if ($dayFajrIqama < $minFajrTime) {
//                 $dayFajrIqama = $minFajrTime;
//             }
            
//             if ($dayFajrIqama > $maxFajrTime) {
//                 $dayFajrIqama = $maxFajrTime;
//             }
            
//             $results[$dayIndex] = $dayFajrIqama;
//         }
        
//         return $results;
//     }

//     /**
//      * Calculate Dhuhr Iqama times for a collection of days
//      * 
//      * @param array $daysData Array of day data with athan times
//      * @param string $dhuhrRule Rule for calculating Dhuhr Iqama
//      * @param int $dhuhrMinutesAfter Minutes after Athan
//      * @param string $dhuhrFixedStandard Fixed time for standard time
//      * @param string $dhuhrFixedDst Fixed time for DST
//      * @param bool $isWeekly Whether to use weekly calculation
//      * @param int $dhuhrRounding Rounding interval in minutes
//      * @return array Array of DateTime objects indexed by day index
//      */
//     public static function calculateDhuhrIqama(
//         array $daysData,
//         string $dhuhrRule,
//         int $dhuhrMinutesAfter,
//         string $dhuhrFixedStandard,
//         string $dhuhrFixedDst,
//         bool $isWeekly,
//         int $dhuhrRounding
//     ): array {
//         $results = [];
        
//         // Normalize all times
//         $normalizedDaysData = TimeHelpers::normalizeTimesForDst($daysData);
        
//         // Find latest Athan for weekly calculation
//         $latestDhuhr = null;
        
//         if ($isWeekly) {
//             foreach ($normalizedDaysData as $dayData) {
//                 $dayDhuhrAthan = $dayData['athan']['dhuhr'];
//                 if ($latestDhuhr === null || TimeHelpers::timeToMinutes($dayDhuhrAthan) > TimeHelpers::timeToMinutes($latestDhuhr)) {
//                     $latestDhuhr = clone $dayDhuhrAthan;
//                 }
//             }
            
//             // Apply rounding to the latest time
//             $latestDhuhr = TimeHelpers::roundUp($latestDhuhr, $dhuhrRounding);
//         }
        
//         // Process each day
//         foreach ($normalizedDaysData as $dayIndex => $dayData) {
//             $dayDate = $dayData['date'];
//             $dayDhuhrAthan = $dayData['athan']['dhuhr'];
//             $dayIsDst = $daysData[$dayIndex]['date']->format('I') == '1';
            
//             // Determine iqama time based on rule
//             if ($isWeekly) {
//                 $dayDhuhrIqama = clone $latestDhuhr;
//                 $dayDhuhrIqama->modify("+{$dhuhrMinutesAfter} minutes");
//             } else {
//                 if ($dhuhrRule === 'after_athan') {
//                     $dayDhuhrIqama = clone $dayDhuhrAthan;
//                     $dayDhuhrIqama = TimeHelpers::roundUp($dayDhuhrIqama, $dhuhrRounding);
//                     $dayDhuhrIqama->modify("+{$dhuhrMinutesAfter} minutes");
//                 } elseif ($dhuhrRule === 'fixed') {
//                     $fixedTime = $dayIsDst ? $dhuhrFixedDst : $dhuhrFixedStandard;
//                     $dayDhuhrIqama = TimeHelpers::parseTimeString($dayDate, $fixedTime);
//                     $dayDhuhrIqama = TimeHelpers::normalizeTimeForDst($dayDhuhrIqama);
//                 } else {
//                     $dayDhuhrIqama = clone $dayDhuhrAthan;
//                     $dayDhuhrIqama = TimeHelpers::roundUp($dayDhuhrIqama, $dhuhrRounding);
//                     $dayDhuhrIqama->modify("+{$dhuhrMinutesAfter} minutes");
//                 }
//             }
            
//             // Denormalize the result to account for DST before storing
//             $dayDhuhrIqama = TimeHelpers::denormalizeTimeForDst($dayDhuhrIqama);
            
//             $results[$dayIndex] = $dayDhuhrIqama;
//         }
        
//         return $results;
//     }

//     /**
//      * Calculate Asr Iqama times for a collection of days
//      * 
//      * @param array $daysData Array of day data with athan times
//      * @param string $asrRule Rule for calculating Asr Iqama
//      * @param int $asrMinutesAfter Minutes after Athan
//      * @param string $asrFixedStandard Fixed time for standard time
//      * @param string $asrFixedDst Fixed time for DST
//      * @param bool $isWeekly Whether to use weekly calculation
//      * @param int $asrRounding Rounding interval in minutes
//      * @return array Array of DateTime objects indexed by day index
//      */
//     public static function calculateAsrIqama(
//         array $daysData,
//         string $asrRule,
//         int $asrMinutesAfter,
//         string $asrFixedStandard,
//         string $asrFixedDst,
//         bool $isWeekly,
//         int $asrRounding
//     ): array {
//         $results = [];
        
//         // Normalize all times
//         $normalizedDaysData = TimeHelpers::normalizeTimesForDst($daysData);
        
//         // Find latest Athan for weekly calculation
//         $latestAsr = null;
        
//         if ($isWeekly) {
//             foreach ($normalizedDaysData as $dayData) {
//                 $dayAsrAthan = $dayData['athan']['asr'];
//                 if ($latestAsr === null || TimeHelpers::timeToMinutes($dayAsrAthan) > TimeHelpers::timeToMinutes($latestAsr)) {
//                     $latestAsr = clone $dayAsrAthan;
//                 }
//             }
            
//             // Apply rounding to the latest time
//             $latestAsr = TimeHelpers::roundUp($latestAsr, $asrRounding);
//         }
        
//         // Process each day
//         foreach ($normalizedDaysData as $dayIndex => $dayData) {
//             $dayDate = $dayData['date'];
//             $dayAsrAthan = $dayData['athan']['asr'];
//             $dayIsDst = $daysData[$dayIndex]['date']->format('I') == '1';
            
//             // Determine iqama time based on rule
//             if ($isWeekly) {
//                 $dayAsrIqama = clone $latestAsr;
//                 $dayAsrIqama->modify("+{$asrMinutesAfter} minutes");
//             } else {
//                 if ($asrRule === 'after_athan') {
//                     $dayAsrIqama = clone $dayAsrAthan;
//                     $dayAsrIqama = TimeHelpers::roundUp($dayAsrIqama, $asrRounding);
//                     $dayAsrIqama->modify("+{$asrMinutesAfter} minutes");
//                 } elseif ($asrRule === 'fixed') {
//                     $fixedTime = $dayIsDst ? $asrFixedDst : $asrFixedStandard;
//                     $dayAsrIqama = TimeHelpers::parseTimeString($dayDate, $fixedTime);
//                     $dayAsrIqama = TimeHelpers::normalizeTimeForDst($dayAsrIqama);
//                 } else {
//                     $dayAsrIqama = clone $dayAsrAthan;
//                     $dayAsrIqama = TimeHelpers::roundUp($dayAsrIqama, $asrRounding);
//                     $dayAsrIqama->modify("+{$asrMinutesAfter} minutes");
//                 }
//             }
            
//             // Denormalize the result to account for DST before storing
//             $dayAsrIqama = TimeHelpers::denormalizeTimeForDst($dayAsrIqama);
            
//             $results[$dayIndex] = $dayAsrIqama;
//         }
        
//         return $results;
//     }

//     /**
//      * Calculate Maghrib Iqama times for a collection of days
//      * 
//      * @param array $daysData Array of day data with athan times
//      * @param int $maghribMinutesAfter Minutes after Athan
//      * @param bool $isWeekly Whether to use weekly calculation
//      * @param int $maghribRounding Rounding interval in minutes
//      * @return array Array of DateTime objects indexed by day index
//      */
//     public static function calculateMaghribIqama(
//         array $daysData,
//         int $maghribMinutesAfter,
//         bool $isWeekly,
//         int $maghribRounding
//     ): array {
//         $results = [];
        
//         // Normalize all times
//         $normalizedDaysData = TimeHelpers::normalizeTimesForDst($daysData);
        
//         // Find latest Athan for weekly calculation
//         $latestMaghrib = null;
        
//         if ($isWeekly) {
//             foreach ($normalizedDaysData as $dayData) {
//                 $dayMaghribAthan = $dayData['athan']['maghrib'];
//                 if ($latestMaghrib === null || TimeHelpers::timeToMinutes($dayMaghribAthan) > TimeHelpers::timeToMinutes($latestMaghrib)) {
//                     $latestMaghrib = clone $dayMaghribAthan;
//                 }
//             }
            
//             // Apply rounding to the latest time
//             if ($latestMaghrib) {
//                 $latestMaghrib = TimeHelpers::roundUp($latestMaghrib, $maghribRounding);
//             }
//         }
        
//         // Process each day
//         foreach ($normalizedDaysData as $dayIndex => $dayData) {
//             $dayDate = $dayData['date'];
//             $dayMaghribAthan = $dayData['athan']['maghrib'];
            
//             // Maghrib is always calculated as minutes after Athan
//             if ($isWeekly) {
//                 $dayMaghribIqama = clone $latestMaghrib;
//                 $dayMaghribIqama->modify("+{$maghribMinutesAfter} minutes");
//             } else {
//                 $dayMaghribIqama = clone $dayMaghribAthan;
//                 $dayMaghribIqama = TimeHelpers::roundUp($dayMaghribIqama, $maghribRounding);
//                 $dayMaghribIqama->modify("+{$maghribMinutesAfter} minutes");
//             }
            
//             // Denormalize the result to account for DST before storing
//             $dayMaghribIqama = TimeHelpers::denormalizeTimeForDst($dayMaghribIqama);
            
//             $results[$dayIndex] = $dayMaghribIqama;
//         }
        
//         return $results;
//     }

//     /**
//      * Calculate Isha Iqama times for a collection of days
//      * 
//      * @param array $daysData Array of day data with athan times
//      * @param string $ishaRule Rule for calculating Isha Iqama
//      * @param int $ishaMinutesAfter Minutes after Athan
//      * @param string $ishaMinTime Minimum time constraint (HH:MM)
//      * @param string $ishaMaxTime Maximum time constraint (HH:MM)
//      * @param bool $isWeekly Whether to use weekly calculation
//      * @param int $ishaRounding Rounding interval in minutes
//      * @return array Array of DateTime objects indexed by day index
//      */
//     public static function calculateIshaIqama(
//         array $daysData,
//         string $ishaRule,
//         int $ishaMinutesAfter,
//         string $ishaMinTime,
//         string $ishaMaxTime,
//         bool $isWeekly,
//         int $ishaRounding
//     ): array {
//         $results = [];
        
//         // Normalize all times
//         $normalizedDaysData = TimeHelpers::normalizeTimesForDst($daysData);
        
//         // Find latest Athan for weekly calculation
//         $latestIsha = null;
        
//         if ($isWeekly) {
//             foreach ($normalizedDaysData as $dayData) {
//                 $dayIshaAthan = $dayData['athan']['isha'];
//                 if ($latestIsha === null || TimeHelpers::timeToMinutes($dayIshaAthan) > TimeHelpers::timeToMinutes($latestIsha)) {
//                     $latestIsha = clone $dayIshaAthan;
//                 }
//             }
            
//             // Apply rounding to the latest time
//             if ($latestIsha) {
//                 $latestIsha = TimeHelpers::roundUp($latestIsha, $ishaRounding);
//             }
//         }
        
//         // Process each day
//         foreach ($normalizedDaysData as $dayIndex => $dayData) {
//             $dayDate = $dayData['date'];
//             $dayIshaAthan = $dayData['athan']['isha'];
            
//             // Determine iqama time based on rule
//             if ($isWeekly) {
//                 $dayIshaIqama = clone $latestIsha;
//                 $dayIshaIqama->modify("+{$ishaMinutesAfter} minutes");
//             } else {
//                 $dayIshaIqama = clone $dayIshaAthan;
//                 $dayIshaIqama = TimeHelpers::roundUp($dayIshaIqama, $ishaRounding);
//                 $dayIshaIqama->modify("+{$ishaMinutesAfter} minutes");
//             }
            
//             // Denormalize the result to account for DST before applying constraints
//             $dayIshaIqama = TimeHelpers::denormalizeTimeForDst($dayIshaIqama);
            
//             // Create min and max time constraints
//             $minIshaTime = TimeHelpers::parseTimeString($dayDate, $ishaMinTime);
//             $maxIshaTime = TimeHelpers::parseTimeString($dayDate, $ishaMaxTime);
            
//             // Apply minimum/maximum constraints
//             if ($dayIshaIqama < $minIshaTime) {
//                 $dayIshaIqama = $minIshaTime;
//             }
            
//             if ($dayIshaIqama > $maxIshaTime) {
//                 $dayIshaIqama = $maxIshaTime;
//             }
            
//             $results[$dayIndex] = $dayIshaIqama;
//         }
        
//         return $results;
//     }
// }
