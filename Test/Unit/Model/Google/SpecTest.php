<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Test\Unit\Model\Google;

use Dlabsit\GoogleFeed\Model\Google\Spec;
use PHPUnit\Framework\TestCase;

class SpecTest extends TestCase
{
    private Spec $spec;

    protected function setUp(): void
    {
        $this->spec = new Spec();
    }

    public function testFormatPriceUsesPeriodSpaceAndUppercaseCurrency(): void
    {
        $this->assertSame('99.00 EUR', $this->spec->formatPrice(99.0, 'EUR'));
        $this->assertSame('1234.50 USD', $this->spec->formatPrice(1234.5, 'usd'));
        $this->assertSame('0.99 GBP', $this->spec->formatPrice(0.99, 'GBP'));
    }

    public function testFormatWeightKgTrimsTrailingZeros(): void
    {
        $this->assertSame('1.5 kg', $this->spec->formatWeightKg(1500));
        $this->assertSame('1 kg', $this->spec->formatWeightKg(1000));
        $this->assertSame('0.25 kg', $this->spec->formatWeightKg(250));
    }

    public function testFormatWeightKgReturnsNullForNonPositive(): void
    {
        $this->assertNull($this->spec->formatWeightKg(0));
        $this->assertNull($this->spec->formatWeightKg(-5));
    }

    public function testAvailabilityUsesUnderscoresNotSpaces(): void
    {
        // Regression guard: legacy "in stock" (space) is NOT valid.
        $this->assertSame('in_stock', Spec::AVAILABILITY_IN_STOCK);
        $this->assertTrue($this->spec->isValidAvailability('in_stock'));
        $this->assertFalse($this->spec->isValidAvailability('in stock'));
        $this->assertFalse($this->spec->isValidAvailability('InStock'));
    }

    public function testValidValueSets(): void
    {
        $this->assertTrue($this->spec->isValidCondition('refurbished'));
        $this->assertFalse($this->spec->isValidCondition('brand-new'));
        $this->assertTrue($this->spec->isValidGender('unisex'));
        $this->assertFalse($this->spec->isValidGender('x'));
        $this->assertTrue($this->spec->isValidAgeGroup('toddler'));
        $this->assertFalse($this->spec->isValidAgeGroup('teen'));
    }

    public function testTruncateIsMultibyteSafe(): void
    {
        $this->assertSame('abc', $this->spec->truncate('abcdef', 3));
        $this->assertSame('abcdef', $this->spec->truncate('abcdef', 10));
        $this->assertSame('héllo', $this->spec->truncate('héllo wörld', 5));
    }

    public function testMeaningfulBrandRejectsPlaceholders(): void
    {
        $this->assertTrue($this->spec->isMeaningfulBrand('Bosch'));
        $this->assertFalse($this->spec->isMeaningfulBrand(''));
        $this->assertFalse($this->spec->isMeaningfulBrand('N/A'));
        $this->assertFalse($this->spec->isMeaningfulBrand('generic'));
        $this->assertFalse($this->spec->isMeaningfulBrand('OEM'));
    }
}
