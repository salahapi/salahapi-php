<?php

namespace SalahAPI\Tests;

use PHPUnit\Framework\TestCase;
use SalahAPI\SalahAPI;
use SalahAPI\Info;
use SalahAPI\Contact;
use SalahAPI\Location;
use SalahAPI\CalculationMethod;
use SalahAPI\IqamaCalculationRules;
use SalahAPI\PrayerCalculationRule;
use SalahAPI\PrayerCalculationOverrideRule;
use SalahAPI\JumuahRule;
use SalahAPI\JumuahLocation;
use SalahAPI\DailyPrayerTimes;
use SalahAPI\CsvUrlParameters;

class SalahAPITest extends TestCase
{
    public function testCreateBasicDocument()
    {
        $document = new SalahAPI('1.0');
        
        $this->assertEquals('1.0', $document->salahapi);
        $this->assertNull($document->info);
        $this->assertNull($document->location);
        $this->assertNull($document->calculationMethod);
        $this->assertNull($document->dailyPrayerTimes);
    }

    public function testCreateDocumentWithInfo()
    {
        $contact = new Contact('Support', 'support@example.com');
        $info = new Info(
            'New York Islamic Center Prayer Times',
            'Prayer times for New York City using ISNA calculation method',
            '1.0.0',
            $contact
        );
        
        $document = new SalahAPI('1.0', $info);
        
        $this->assertNotNull($document->info);
        $this->assertEquals('New York Islamic Center Prayer Times', $document->info->title);
        $this->assertEquals('support@example.com', $document->info->contact->email);
    }

    public function testCreateDocumentWithLocation()
    {
        $location = new Location(
            40.7128,
            -74.0060,
            'America/New_York',
            'YYYY-MM-DD',
            'HH:mm:ss',
            'New York',
            'United States'
        );
        
        $document = new SalahAPI('1.0', null, $location);
        
        $this->assertNotNull($document->location);
        $this->assertEquals(40.7128, $document->location->latitude);
        $this->assertEquals(-74.0060, $document->location->longitude);
        $this->assertEquals('America/New_York', $document->location->timezone);
        $this->assertEquals('New York', $document->location->city);
    }

    public function testCreateDocumentWithCalculationMethod()
    {
        $calculationMethod = new CalculationMethod(
            'ISNA',
            15.0,
            15.0,
            'Standard',
            'MiddleOfTheNight'
        );
        
        $document = new SalahAPI('1.0', null, null, $calculationMethod);
        
        $this->assertNotNull($document->calculationMethod);
        $this->assertEquals('ISNA', $document->calculationMethod->name);
        $this->assertEquals(15.0, $document->calculationMethod->fajrAngle);
        $this->assertEquals(15.0, $document->calculationMethod->ishaAngle);
    }

    public function testCreateDocumentWithDailyPrayerTimes()
    {
        $csvUrlParameters = new CsvUrlParameters();
        $csvUrlParameters->addDateParameter('fromDate', 'query', 'fromDate', 'YYYY-MM-DD');
        $csvUrlParameters->addDateParameter('toDate', 'query', 'toDate', 'YYYY-MM-DD');
        $csvUrlParameters->addStaticParameter('apiVersion', 'query', '2.0');
        
        $dailyPrayerTimes = new DailyPrayerTimes(
            'https://example.com/prayer_times',
            'YYYY-MM-DD',
            'HH:mm:ss',
            $csvUrlParameters
        );
        
        $document = new SalahAPI('1.0', null, null, null, $dailyPrayerTimes);
        
        $this->assertNotNull($document->dailyPrayerTimes);
        $this->assertEquals('https://example.com/prayer_times', $document->dailyPrayerTimes->csvUrl);
        $this->assertEquals('YYYY-MM-DD', $document->dailyPrayerTimes->dateFormat);
    }

    public function testCreateDocumentWithIqamaRules()
    {
        $fajrRule = new PrayerCalculationRule(
            null,
            'daily',
            15,
            '04:00',
            '06:45',
            null,
            30
        );
        
        $dhuhrRule = new PrayerCalculationRule('12:30');
        
        $iqamaRules = new IqamaCalculationRules(
            'friday',
            $fajrRule,
            $dhuhrRule
        );
        
        $calculationMethod = new CalculationMethod(
            'ISNA',
            15.0,
            15.0,
            'Standard',
            'MiddleOfTheNight',
            $iqamaRules
        );
        
        $document = new SalahAPI('1.0', null, null, $calculationMethod);
        
        $this->assertNotNull($document->calculationMethod->iqamaCalculationRules);
        $this->assertEquals('friday', $document->calculationMethod->iqamaCalculationRules->changeOn);
        $this->assertEquals('daily', $document->calculationMethod->iqamaCalculationRules->fajr->change);
        $this->assertEquals('12:30', $document->calculationMethod->iqamaCalculationRules->dhuhr->static);
    }

    public function testCreateDocumentWithJumuahRules()
    {
        $location = new JumuahLocation('New York Islamic Center', '123 Main St, New York, NY 10001');
        $time = new PrayerCalculationRule('12:00');
        $jumuahRule = new JumuahRule('Jumuah 1', $time, $location);
        
        $calculationMethod = new CalculationMethod(
            'ISNA',
            15.0,
            15.0,
            'Standard',
            'MiddleOfTheNight',
            null,
            [$jumuahRule]
        );
        
        $document = new SalahAPI('1.0', null, null, $calculationMethod);
        
        $this->assertNotNull($document->calculationMethod->jumuahRules);
        $this->assertCount(1, $document->calculationMethod->jumuahRules);
        $this->assertEquals('Jumuah 1', $document->calculationMethod->jumuahRules[0]->name);
        $this->assertEquals('12:00', $document->calculationMethod->jumuahRules[0]->time->static);
    }

    public function testDocumentToArray()
    {
        $contact = new Contact('Support', 'support@example.com');
        $info = new Info('Test', 'Description', '1.0.0', $contact);
        $document = new SalahAPI('1.0', $info);
        
        $array = $document->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals('1.0', $array['salahapi']);
        $this->assertArrayHasKey('info', $array);
        $this->assertEquals('Test', $array['info']['title']);
        $this->assertEquals('support@example.com', $array['info']['contact']['email']);
    }

    public function testDocumentToJson()
    {
        $contact = new Contact('Support', 'support@example.com');
        $info = new Info('Test', 'Description', '1.0.0', $contact);
        $document = new SalahAPI('1.0', $info);
        
        $json = $document->toJson();
        
        $this->assertIsString($json);
        $this->assertJson($json);
        
        $decoded = json_decode($json, true);
        $this->assertEquals('1.0', $decoded['salahapi']);
        $this->assertEquals('Test', $decoded['info']['title']);
    }

    public function testDocumentFromArray()
    {
        $data = [
            'salahapi' => '1.0',
            'info' => [
                'title' => 'Test',
                'description' => 'Description',
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'Support',
                    'email' => 'support@example.com'
                ]
            ]
        ];
        
        $document = SalahAPI::fromArray($data);
        
        $this->assertEquals('1.0', $document->salahapi);
        $this->assertNotNull($document->info);
        $this->assertEquals('Test', $document->info->title);
        $this->assertEquals('support@example.com', $document->info->contact->email);
    }

    public function testDocumentFromJson()
    {
        $json = '{
            "salahapi": "1.0",
            "info": {
                "title": "Test",
                "description": "Description",
                "version": "1.0.0",
                "contact": {
                    "name": "Support",
                    "email": "support@example.com"
                }
            }
        }';
        
        $document = SalahAPI::fromJson($json);
        
        $this->assertEquals('1.0', $document->salahapi);
        $this->assertNotNull($document->info);
        $this->assertEquals('Test', $document->info->title);
    }

    public function testPrayerCalculationRuleWithOverrides()
    {
        $overrideRule = new PrayerCalculationRule('13:30');
        $override = new PrayerCalculationOverrideRule('daylightSavingsTime', $overrideRule);
        
        $rule = new PrayerCalculationRule('12:30', null, null, null, null, null, null, [$override]);
        
        $this->assertNotNull($rule->overrides);
        $this->assertCount(1, $rule->overrides);
        $this->assertEquals('daylightSavingsTime', $rule->overrides[0]->condition);
        $this->assertEquals('13:30', $rule->overrides[0]->time->static);
    }

    public function testComplexDocumentRoundTrip()
    {
        // Create a complex document
        $contact = new Contact('Support', 'support@example.com');
        $info = new Info(
            'New York Islamic Center Prayer Times',
            'Prayer times for New York City using ISNA calculation method',
            '1.0.0',
            $contact
        );
        
        $location = new Location(
            40.7128,
            -74.0060,
            'America/New_York',
            'YYYY-MM-DD',
            'HH:mm:ss',
            'New York',
            'United States'
        );
        
        $fajrRule = new PrayerCalculationRule(null, 'daily', 15, '04:00', '06:45', null, 30);
        $dhuhrRule = new PrayerCalculationRule('12:30');
        
        $iqamaRules = new IqamaCalculationRules(
            'friday',
            $fajrRule,
            $dhuhrRule
        );
        
        $jumuahLocation = new JumuahLocation('New York Islamic Center', '123 Main St, New York, NY 10001');
        $jumuahTime = new PrayerCalculationRule('12:00');
        $jumuahRule = new JumuahRule('Jumuah 1', $jumuahTime, $jumuahLocation);
        
        $calculationMethod = new CalculationMethod(
            'ISNA',
            15.0,
            15.0,
            'Standard',
            'MiddleOfTheNight',
            $iqamaRules,
            [$jumuahRule]
        );
        
        $document = new SalahAPI('1.0', $info, $location, $calculationMethod);
        
        // Convert to JSON and back
        $json = $document->toJson();
        $restoredDocument = SalahAPI::fromJson($json);
        
        // Verify the restored document
        $this->assertEquals($document->salahapi, $restoredDocument->salahapi);
        $this->assertEquals($document->info->title, $restoredDocument->info->title);
        $this->assertEquals($document->location->latitude, $restoredDocument->location->latitude);
        $this->assertEquals($document->calculationMethod->name, $restoredDocument->calculationMethod->name);
        $this->assertEquals(
            $document->calculationMethod->iqamaCalculationRules->changeOn,
            $restoredDocument->calculationMethod->iqamaCalculationRules->changeOn
        );
        $this->assertCount(1, $restoredDocument->calculationMethod->jumuahRules);
    }
}

