<?php

namespace SalahAPI\Tests;

use PHPUnit\Framework\TestCase;
use SalahAPI\CalculationMethod;

/**
 * CalculationMethod Tests
 *
 * @package SalahAPI\Tests
 */
class CalculationMethodTest extends TestCase
{
    /**
     * Test that asrIqamaCalculationMethod is null by default
     */
    public function testAsrIqamaCalculationMethodDefaultsToNull()
    {
        $method = new CalculationMethod('MWL');

        $this->assertNull($method->asrIqamaCalculationMethod);
    }

    /**
     * Test that asrIqamaCalculationMethod can be set via the constructor
     */
    public function testAsrIqamaCalculationMethodFromConstructor()
    {
        $method = new CalculationMethod(
            'MWL',
            18.0,
            17.0,
            'standard',
            null,
            null,
            null,
            'hanafi'
        );

        $this->assertEquals('standard', $method->asrCalculationMethod);
        $this->assertEquals('hanafi', $method->asrIqamaCalculationMethod);
    }

    /**
     * Test toArray omits asrIqamaCalculationMethod when not set
     */
    public function testToArrayOmitsAsrIqamaCalculationMethodWhenNull()
    {
        $method = new CalculationMethod('MWL', 18.0, 17.0, 'standard');

        $data = $method->toArray();

        $this->assertArrayHasKey('asrCalculationMethod', $data);
        $this->assertArrayNotHasKey('asrIqamaCalculationMethod', $data);
    }

    /**
     * Test toArray includes asrIqamaCalculationMethod when set
     */
    public function testToArrayIncludesAsrIqamaCalculationMethod()
    {
        $method = new CalculationMethod('MWL', 18.0, 17.0, 'standard', null, null, null, 'hanafi');

        $data = $method->toArray();

        $this->assertEquals('standard', $data['asrCalculationMethod']);
        $this->assertEquals('hanafi', $data['asrIqamaCalculationMethod']);
    }

    /**
     * Test fromArray reads asrIqamaCalculationMethod
     */
    public function testFromArrayReadsAsrIqamaCalculationMethod()
    {
        $method = CalculationMethod::fromArray([
            'name' => 'isna',
            'fajrAngle' => 15,
            'ishaAngle' => 15,
            'asrCalculationMethod' => 'standard',
            'asrIqamaCalculationMethod' => 'hanafi',
        ]);

        $this->assertEquals('isna', $method->name);
        $this->assertEquals('standard', $method->asrCalculationMethod);
        $this->assertEquals('hanafi', $method->asrIqamaCalculationMethod);
    }

    /**
     * Test fromArray defaults asrIqamaCalculationMethod to null when omitted
     */
    public function testFromArrayDefaultsAsrIqamaCalculationMethodToNull()
    {
        $method = CalculationMethod::fromArray([
            'name' => 'isna',
            'asrCalculationMethod' => 'standard',
        ]);

        $this->assertNull($method->asrIqamaCalculationMethod);
    }

    /**
     * Test round-trip serialization preserves asrIqamaCalculationMethod
     */
    public function testArrayRoundTripPreservesAsrIqamaCalculationMethod()
    {
        $method = new CalculationMethod('MWL', 18.0, 17.0, 'standard', null, null, null, 'hanafi');

        $restored = CalculationMethod::fromArray($method->toArray());

        $this->assertEquals($method->asrCalculationMethod, $restored->asrCalculationMethod);
        $this->assertEquals($method->asrIqamaCalculationMethod, $restored->asrIqamaCalculationMethod);
    }
}
