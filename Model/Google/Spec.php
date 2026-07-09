<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Google;

/**
 * Single source of truth for the Google Merchant Center product data
 * specification. Pure PHP — knows nothing about Magento.
 *
 * All valid-value sets, character limits and value formatters live HERE and
 * nowhere else, so spec drift can only ever happen in one file. This is a
 * deliberate reaction to the previous module, where wrong spec facts were
 * scattered across writers.
 *
 * Verified against (2026):
 * - Product data specification  support.google.com/merchants/answer/7052112
 * - RSS 2.0 data source          support.google.com/merchants/answer/14987622
 * - 2026 spec update             support.google.com/merchants/answer/16989427
 */
class Spec
{
    /** Google namespace URI for all g:-prefixed attributes. */
    public const NAMESPACE_URI = 'http://base.google.com/ns/1.0';
    public const NAMESPACE_PREFIX = 'g';

    /**
     * The three item-level elements that are standard RSS 2.0 and are written
     * WITHOUT the g: prefix. Everything else is g:-prefixed.
     */
    public const RSS_PLAIN_ELEMENTS = ['title', 'link', 'description'];

    // --- availability [availability] — underscores, NOT spaces (legacy "in stock" is wrong) ---
    public const AVAILABILITY_IN_STOCK = 'in_stock';
    public const AVAILABILITY_OUT_OF_STOCK = 'out_of_stock';
    public const AVAILABILITY_PREORDER = 'preorder';
    public const AVAILABILITY_BACKORDER = 'backorder';
    public const AVAILABILITY_VALUES = [
        self::AVAILABILITY_IN_STOCK,
        self::AVAILABILITY_OUT_OF_STOCK,
        self::AVAILABILITY_PREORDER,
        self::AVAILABILITY_BACKORDER,
    ];

    // --- condition [condition] ---
    public const CONDITION_VALUES = ['new', 'refurbished', 'used'];

    // --- gender [gender] ---
    public const GENDER_VALUES = ['male', 'female', 'unisex'];

    // --- age_group [age_group] ---
    public const AGE_GROUP_VALUES = ['newborn', 'infant', 'toddler', 'kids', 'adult'];

    // --- character limits (from the product data spec) ---
    public const LIMIT_ID = 50;
    public const LIMIT_TITLE = 150;
    public const LIMIT_DESCRIPTION = 5000;
    public const LIMIT_LINK = 2000;
    public const LIMIT_BRAND = 70;
    public const LIMIT_MPN = 70;
    public const LIMIT_PRODUCT_TYPE = 750;
    public const LIMIT_COLOR = 100;
    public const LIMIT_SIZE = 100;
    public const LIMIT_ITEM_GROUP_ID = 50;
    public const MAX_ADDITIONAL_IMAGES = 10;

    /** Weight units accepted by g:shipping_weight / g:product_weight. */
    public const WEIGHT_UNITS = ['lb', 'oz', 'g', 'kg'];

    /**
     * Format a monetary value the way Google requires: number, single space,
     * ISO 4217 currency code, period as the decimal separator, no thousands
     * separator. e.g. formatPrice(99.0, 'EUR') => "99.00 EUR".
     */
    public function formatPrice(float $amount, string $currency): string
    {
        return number_format($amount, 2, '.', '') . ' ' . strtoupper($currency);
    }

    /**
     * Format a weight in grams to a Google weight value in kilograms, e.g.
     * formatWeightKg(1500) => "1.5 kg". Returns null for non-positive input.
     */
    public function formatWeightKg(int $grams): ?string
    {
        if ($grams <= 0) {
            return null;
        }
        // Trim trailing zeros so 1500 -> "1.5 kg", 1000 -> "1 kg".
        $kg = rtrim(rtrim(number_format($grams / 1000, 3, '.', ''), '0'), '.');
        return $kg . ' kg';
    }

    /** True when $value is one of the spec's valid values for that field. */
    public function isValidAvailability(string $value): bool
    {
        return in_array($value, self::AVAILABILITY_VALUES, true);
    }

    public function isValidCondition(string $value): bool
    {
        return in_array($value, self::CONDITION_VALUES, true);
    }

    public function isValidGender(string $value): bool
    {
        return in_array($value, self::GENDER_VALUES, true);
    }

    public function isValidAgeGroup(string $value): bool
    {
        return in_array($value, self::AGE_GROUP_VALUES, true);
    }

    /** Multibyte-safe truncation to the given character limit. */
    public function truncate(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) : $value;
    }

    /**
     * A brand value is only meaningful when it is a real brand. Google rejects
     * placeholders like "N/A" or "Generic"; we also treat the catalogue's "OEM"
     * fallback as no-brand.
     */
    public function isMeaningfulBrand(string $brand): bool
    {
        $brand = trim($brand);
        if ($brand === '') {
            return false;
        }
        return !in_array(strtolower($brand), ['n/a', 'na', 'generic', 'oem', 'none'], true);
    }
}
