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
 * the `dlabsit_googlefeed/*` config section (see etc/adminhtml/system.xml).
 */
class Config extends AbstractHelper
{
    private const P = 'dlabsit_googlefeed/';

    private function value(string $path, ?int $storeId): ?string
    {
        $value = $this->scopeConfig->getValue(
            self::P . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        return $value === null ? null : (string) $value;
    }

    private function flag(string $path, ?int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::P . $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
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

    // --- attribute mapping ---

    public function getUniqueIdSource(?int $storeId = null): string
    {
        $v = (string) $this->value('attributes/unique_id_source', $storeId);
        return $v !== '' ? $v : 'sku';
    }

    public function getCustomAttributeCode(?int $storeId = null): string
    {
        return (string) $this->value('attributes/custom_attribute_code', $storeId);
    }

    public function getBrandAttribute(?int $storeId = null): string
    {
        return (string) $this->value('attributes/brand_attribute', $storeId);
    }

    public function getGtinAttribute(?int $storeId = null): string
    {
        return (string) $this->value('attributes/gtin_attribute', $storeId);
    }

    public function getMpnAttribute(?int $storeId = null): string
    {
        return (string) $this->value('attributes/mpn_attribute', $storeId);
    }

    public function getColorAttribute(?int $storeId = null): string
    {
        return (string) $this->value('attributes/color_attribute', $storeId);
    }

    public function getSizeAttribute(?int $storeId = null): string
    {
        return (string) $this->value('attributes/size_attribute', $storeId);
    }

    public function getWeightAttribute(?int $storeId = null): string
    {
        $v = (string) $this->value('attributes/weight_attribute', $storeId);
        return $v !== '' ? $v : 'weight';
    }

    public function getDescriptionSource(?int $storeId = null): string
    {
        $v = (string) $this->value('attributes/description_source', $storeId);
        return $v !== '' ? $v : 'description';
    }

    // --- product defaults ---

    public function getCondition(?int $storeId = null): string
    {
        $v = (string) $this->value('product/condition', $storeId);
        return $v !== '' ? $v : 'new';
    }

    public function getDefaultGoogleCategory(?int $storeId = null): string
    {
        return (string) $this->value('product/default_google_category', $storeId);
    }

    public function getDefaultGender(?int $storeId = null): string
    {
        return (string) $this->value('product/default_gender', $storeId);
    }

    public function getDefaultAgeGroup(?int $storeId = null): string
    {
        return (string) $this->value('product/default_age_group', $storeId);
    }

    public function includeShippingWeight(?int $storeId = null): bool
    {
        return $this->flag('product/include_shipping_weight', $storeId);
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
