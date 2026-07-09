<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Test\Unit\Model\Google\Validator;

use Dlabsit\GoogleFeed\Model\Google\Validator\Gtin;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GtinTest extends TestCase
{
    private Gtin $gtin;

    protected function setUp(): void
    {
        $this->gtin = new Gtin();
    }

    #[DataProvider('validGtins')]
    public function testValidGtinsPass(string $value): void
    {
        $this->assertTrue($this->gtin->isValid($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function validGtins(): array
    {
        return [
            'EAN-13' => ['4006381333931'],
            'UPC-A 12' => ['036000291452'],
            'EAN-8' => ['96385074'],
            'with spaces/dashes' => ['4006381-333931'],
        ];
    }

    #[DataProvider('invalidGtins')]
    public function testInvalidGtinsFail(string $value): void
    {
        $this->assertFalse($this->gtin->isValid($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidGtins(): array
    {
        return [
            'bad check digit' => ['4006381333930'],
            'wrong length 10' => ['1234567890'],
            'empty' => [''],
            'non-numeric' => ['ABCDEFGH'],
        ];
    }

    public function testNormalizeStripsNonDigits(): void
    {
        $this->assertSame('4006381333931', $this->gtin->normalize('4006381-333931'));
        $this->assertSame('96385074', $this->gtin->normalize(' 9638 5074 '));
    }
}
