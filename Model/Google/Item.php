<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Google;

/**
 * Plain data holder for one Google Merchant `<item>`. The Mapper fills it
 * (applying Spec formatting/limits); the FeedWriter serialises it. Values are
 * already spec-formatted strings — the writer does no formatting itself.
 */
class Item
{
    // Required
    public string $id = '';
    public string $title = '';
    public string $description = '';
    public string $link = '';
    public string $imageLink = '';
    public string $availability = Spec::AVAILABILITY_IN_STOCK;
    public string $price = '';
    public string $condition = 'new';

    /** @var string[] up to Spec::MAX_ADDITIONAL_IMAGES */
    public array $additionalImageLinks = [];

    // Optional
    public ?string $salePrice = null;
    public ?string $salePriceEffectiveDate = null;
    public ?string $brand = null;
    public ?string $gtin = null;
    public ?string $mpn = null;
    public bool $identifierExists = true;
    public ?string $googleProductCategory = null;
    public ?string $productType = null;
    public ?string $itemGroupId = null;
    public ?string $color = null;
    public ?string $size = null;
    public ?string $gender = null;
    public ?string $ageGroup = null;
    public ?string $shippingWeight = null;
    public ?string $productLength = null;
    public ?string $productWidth = null;
    public ?string $productHeight = null;

    /** @var array{country: string, service: string, price: string}|null */
    public ?array $shipping = null;

    /** @var array<int, string> custom_label_0 … custom_label_4 (index => value) */
    public array $customLabels = [];

    /** @var array<int, array{section: string, name: string, value: string}> */
    public array $productDetails = [];

    /** @var string[] product_highlight values */
    public array $productHighlights = [];
}
