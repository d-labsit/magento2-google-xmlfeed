<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Google\Validator;

/**
 * Validates a GTIN (g:gtin) the way Google does: after stripping non-digits it
 * must be 8, 12, 13 or 14 digits and carry a correct GS1 mod-10 check digit.
 *
 * Sending an invalid GTIN gets a product disapproved, so the mapper drops any
 * value that does not pass this check rather than exporting it.
 */
class Gtin
{
    private const VALID_LENGTHS = [8, 12, 13, 14];

    public function isValid(string $gtin): bool
    {
        $digits = preg_replace('/\D/', '', $gtin) ?? '';
        $len = strlen($digits);
        if (!in_array($len, self::VALID_LENGTHS, true)) {
            return false;
        }

        // Right-align to 14 digits; the last digit is the check digit. Google's
        // GTIN check digit alternates weights 3 and 1 from the right (excluding
        // the check digit itself).
        $padded = str_pad($digits, 14, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $weight = ($i % 2 === 0) ? 3 : 1;
            $sum += (int) $padded[$i] * $weight;
        }
        $check = (10 - ($sum % 10)) % 10;

        return $check === (int) $padded[13];
    }

    /** Normalise to digits only (drops spaces/dashes) for output. */
    public function normalize(string $gtin): string
    {
        return preg_replace('/\D/', '', $gtin) ?? '';
    }
}
