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
        
        // For non-static rules with overrides, we need to handle each day based on its effective rule
        // but weekly calculations for non-override days should use the full week context
        if ($rule->overrides !== null && !empty($rule->overrides)) {
            // Check which days have overrides
            $daysWithOverrides = [];
            $daysWithoutOverrides = [];
            
            foreach ($daysData as $dayIndex => $dayData) {
                $dayDate = $dayData['date'];
                $effectiveRule = self::getEffectiveRule($rule, $dayDate);
                
                if ($effectiveRule !== $rule) {
                    // This day has an override
                    $daysWithOverrides[$dayIndex] = [
                        'data' => $dayData,
                        'rule' => $effectiveRule
                    ];
                } else {
                    // This day uses the base rule
                    $daysWithoutOverrides[$dayIndex] = $dayData;
                }
            }
            
            // Calculate iqama for days WITHOUT overrides using the base rule
            // For weekly calculations, this will consider only non-override days in the week
            if (!empty($daysWithoutOverrides)) {
                $baseResults = self::calculateIqamaWithRule(
                    $daysWithoutOverrides,
                    $prayerName,
                    $rule,
                    $endPrayerName
                );
                $results = $results + $baseResults;
            }
            
            // Calculate iqama for days WITH overrides using their respective override rules
            foreach ($daysWithOverrides as $dayIndex => $dayInfo) {
                $overrideResults = self::calculateIqamaWithRule(
                    [$dayIndex => $dayInfo['data']],
                    $prayerName,
                    $dayInfo['rule'],
                    $endPrayerName
                );
                $results = $results + $overrideResults;
            }
            
            // Sort results by day index
            ksort($results);
            return $results;
        }
        
        // No overrides, calculate using the base rule
        return self::calculateIqamaWithRule($daysData, $prayerName, $rule, $endPrayerName);
    }

    /**
     * Calculate Iqama times using a specific rule (no override logic)
     * 
     * @param array $daysData Array of day data with athan times to calculate
     * @param string $prayerName Name of the prayer
     * @param PrayerCalculationRule $rule The calculation rule to apply
     * @param string|null $endPrayerName Optional name of the prayer that marks the end of the timeframe
     * @return array Array of DateTime objects indexed by day index
     */
    private static function calculateIqamaWithRule(
        array $daysData,
        string $prayerName,
        PrayerCalculationRule $rule,
        ?string $endPrayerName = null
    ): array {
        $results = [];
        
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
     * Resolves overrides based on conditions (e.g., daylight savings time, ramadan)
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
        $isRamadan = HijriDateConverter::isRamadan($date);
        
        foreach ($baseRule->overrides as $override) {
            if ($override->condition === 'daylightSavingsTime' && $isDst) {
                return $override->time;
            }
            if ($override->condition === 'ramadan' && $isRamadan) {
                return $override->time;
            }
            // Future: Add more conditions as needed (dateRange, etc.)
        }
        
        return $baseRule;
    }
}