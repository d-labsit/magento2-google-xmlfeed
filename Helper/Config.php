<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

/**
 * Store-scoped configuration reader for the Google feed. All values live under
 * the `dlabsit_googlefeed/*` config section (see etc/adminhtml/system.xml),
 * grouped: general · identity · content · categorization · labels · details ·
 * dimensions · shipping.
 */
class Config extends AbstractHelper
{
    private const P = 'dlabsit_googlefeed/';

    public const CUSTOM_LABEL_COUNT = 5;

    private function value(string $path, ?int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue(self::P . $path, ScopeInterface::SCOPE_STORE, $storeId);
        return $value === null ? null : (string) $value;
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::P . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    // --- general ---

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag('general/enabled', $storeId);
    }

    public function getFilename(?int $storeId = null): string
    {
        $name = trim((string) $this->value('general/filename', $storeId));
        return $name !== '' ? $name : 'google.xml';
    }

    public function getStoreName(?int $storeId = null): string
    {
        return (string) $this->value('general/store_name', $storeId);
    }

    public function includeOutOfStock(?int $storeId = null): bool
    {
        return $this->flag('general/include_out_of_stock', $storeId);
    }

    public function getBatchSize(?int $storeId = null): int
    {
        return max(1, (int) $this->value('general/batch_size', $storeId) ?: 500);
    }

    public function getCronSchedule(?int $storeId = null): string
    {
        $schedule = trim((string) $this->value('general/cron_schedule', $storeId));
        return $schedule !== '' ? $schedule : '30 23 * * *';
    }

    // --- identity ---

    public function getUniqueIdSource(?int $storeId = null): string
    {
        $v = (string) $this->value('identity/unique_id_source', $storeId);
        return $v !== '' ? $v : 'sku';
    }

    public function getCustomAttributeCode(?int $storeId = null): string
    {
        return (string) $this->value('identity/custom_attribute_code', $storeId);
    }

    public function getBrandAttribute(?int $storeId = null): string
    {
        return (string) $this->value('identity/brand_attribute', $storeId);
    }

    public function getGtinAttribute(?int $storeId = null): string
    {
        return (string) $this->value('identity/gtin_attribute', $storeId);
    }

    public function getMpnAttribute(?int $storeId = null): string
    {
        return (string) $this->value('identity/mpn_attribute', $storeId);
    }

    // --- content ---

    public function getDescriptionSource(?int $storeId = null): string
    {
        $v = (string) $this->value('content/description_source', $storeId);
        return $v !== '' ? $v : 'description';
    }

    public function getColorAttribute(?int $storeId = null): string
    {
        return (string) $this->value('content/color_attribute', $storeId);
    }

    public function getSizeAttribute(?int $storeId = null): string
    {
        return (string) $this->value('content/size_attribute', $storeId);
    }

    public function getCondition(?int $storeId = null): string
    {
        $v = (string) $this->value('content/condition', $storeId);
        return $v !== '' ? $v : 'new';
    }

    public function getDefaultGender(?int $storeId = null): string
    {
        return (string) $this->value('content/default_gender', $storeId);
    }

    public function getDefaultAgeGroup(?int $storeId = null): string
    {
        return (string) $this->value('content/default_age_group', $storeId);
    }

    // --- categorization ---

    public function getDefaultGoogleCategory(?int $storeId = null): string
    {
        return (string) $this->value('categorization/default_google_category', $storeId);
    }

    // --- labels ---

    /** Product-attribute code whose value fills custom_label_<index> (0..4). */
    public function getCustomLabelAttribute(int $index, ?int $storeId = null): string
    {
        return (string) $this->value('labels/custom_label_' . $index, $storeId);
    }

    // --- details ---

    /**
     * Attribute codes to emit as product_detail entries.
     *
     * @return string[]
     */
    public function getDetailAttributes(?int $storeId = null): array
    {
        $raw = (string) $this->value('details/detail_attributes', $storeId);
        return $raw === '' ? [] : array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function getHighlightAttribute(?int $storeId = null): string
    {
        return (string) $this->value('details/highlight_attribute', $storeId);
    }

    // --- dimensions ---

    public function getWeightAttribute(?int $storeId = null): string
    {
        $v = (string) $this->value('dimensions/weight_attribute', $storeId);
        return $v !== '' ? $v : 'weight';
    }

    public function includeShippingWeight(?int $storeId = null): bool
    {
        return $this->flag('dimensions/include_shipping_weight', $storeId);
    }

    public function getLengthAttribute(?int $storeId = null): string
    {
        return (string) $this->value('dimensions/length_attribute', $storeId);
    }

    public function getWidthAttribute(?int $storeId = null): string
    {
        return (string) $this->value('dimensions/width_attribute', $storeId);
    }

    public function getHeightAttribute(?int $storeId = null): string
    {
        return (string) $this->value('dimensions/height_attribute', $storeId);
    }

    // --- shipping ---

    public function getShippingCountry(?int $storeId = null): string
    {
        return (string) $this->value('shipping/country', $storeId);
    }

    public function getShippingService(?int $storeId = null): string
    {
        return (string) $this->value('shipping/service', $storeId);
    }

    public function getShippingPrice(?int $storeId = null): ?float
    {
        $raw = $this->value('shipping/price', $storeId);
        return ($raw === null || trim($raw) === '') ? null : (float) $raw;
    }
}
