<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Product;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Logger\Logger;
use Dlabsit\GoogleFeed\Model\Config\Source\DescriptionSource;
use Dlabsit\GoogleFeed\Model\Google\Item;
use Dlabsit\GoogleFeed\Model\Google\Spec;
use Dlabsit\GoogleFeed\Model\Google\Validator\Gtin;
use Dlabsit\GoogleFeed\Setup\Patch\Data\AddGoogleCategoryAttributes;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Translates a Magento product into a Google Merchant {@see Item}. All Google
 * value rules (formats, valid values, character limits) are applied through
 * {@see Spec} — this class only reads Magento data and hands it to the Spec.
 */
class Mapper
{
    /** @var array<string, string> category-path cache keyed by category+store */
    private array $categoryPathCache = [];

    public function __construct(
        private readonly Config $config,
        private readonly Spec $spec,
        private readonly Gtin $gtin,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger
    ) {
    }

    /**
     * Build one Google item. For a configurable variant, pass the configurable
     * as $parent and its shared item_group_id so link/image fall back to the
     * parent and all variants group together.
     */
    public function map(
        Product $product,
        int $storeId,
        string $currency,
        ?Product $parent = null,
        ?string $itemGroupId = null
    ): Item {
        $item = new Item();

        $item->id = $this->spec->truncate($this->resolveId($product, $storeId), Spec::LIMIT_ID);
        $item->title = $this->spec->truncate((string) $product->getName(), Spec::LIMIT_TITLE);
        $item->description = $this->spec->truncate(
            $this->resolveDescription($product, $storeId),
            Spec::LIMIT_DESCRIPTION
        );
        $item->link = $this->spec->truncate(
            (string) ($parent ? $parent->getProductUrl() : $product->getProductUrl()),
            Spec::LIMIT_LINK
        );
        $item->imageLink = $this->getImageUrl($product) ?: ($parent ? $this->getImageUrl($parent) : '');
        $item->additionalImageLinks = $this->getAdditionalImages($product);

        $item->availability = $this->resolveAvailability($product, $storeId);
        $item->condition = $this->resolveCondition($storeId);

        $item->price = $this->spec->formatPrice($this->getFinalPrice($product), $currency);
        $special = $this->getSpecialPrice($product);
        if ($special !== null) {
            $item->salePrice = $this->spec->formatPrice($special, $currency);
            $item->salePriceEffectiveDate = $this->resolveSaleWindow($product);
        }

        $this->applyIdentifiers($item, $product, $storeId);
        $this->applyClassification($item, $product, $storeId);
        $this->applyVariantAttributes($item, $product, $storeId);
        $this->applyShipping($item, $product, $parent, $storeId, $currency);
        $this->applyDimensions($item, $product, $storeId);
        $this->applyCustomLabels($item, $product, $storeId);
        $this->applyProductDetails($item, $product, $storeId);
        $this->applyHighlights($item, $product, $storeId);

        if ($itemGroupId !== null && $itemGroupId !== '') {
            $item->itemGroupId = $this->spec->truncate($itemGroupId, Spec::LIMIT_ITEM_GROUP_ID);
        }

        return $item;
    }

    /** Shared item_group_id for a configurable's variants (= its own id). */
    public function getItemGroupId(Product $configurable, int $storeId): string
    {
        return $this->resolveId($configurable, $storeId);
    }

    private function resolveId(Product $product, int $storeId): string
    {
        return match ($this->config->getUniqueIdSource($storeId)) {
            'sku' => (string) $product->getSku(),
            'custom' => $this->readScalar($product, $this->config->getCustomAttributeCode($storeId)),
            default => (string) $product->getId(),
        };
    }

    private function applyIdentifiers(Item $item, Product $product, int $storeId): void
    {
        $brand = trim($this->readOption($product, $this->config->getBrandAttribute($storeId)));
        if ($this->spec->isMeaningfulBrand($brand)) {
            $item->brand = $this->spec->truncate($brand, Spec::LIMIT_BRAND);
        }

        $gtinRaw = trim($this->readScalar($product, $this->config->getGtinAttribute($storeId)));
        if ($gtinRaw !== '' && $this->gtin->isValid($gtinRaw)) {
            $item->gtin = $this->gtin->normalize($gtinRaw);
        }

        $mpn = trim($this->readScalar($product, $this->config->getMpnAttribute($storeId)));
        if ($mpn !== '') {
            $item->mpn = $this->spec->truncate($mpn, Spec::LIMIT_MPN);
        }

        // identifier_exists=no ONLY when there is no brand, no gtin and no mpn.
        $item->identifierExists = $item->brand !== null || $item->gtin !== null || $item->mpn !== null;
    }

    private function applyClassification(Item $item, Product $product, int $storeId): void
    {
        $googleCategory = $this->resolveGoogleCategory($product, $storeId);
        if ($googleCategory !== '') {
            $item->googleProductCategory = $googleCategory;
        }

        $productType = $this->getCategoryPath($product, $storeId);
        if ($productType !== '') {
            $item->productType = $this->spec->truncate($productType, Spec::LIMIT_PRODUCT_TYPE);
        }
    }

    /**
     * Resolve google_product_category with a clear precedence:
     *   1. product-level override attribute
     *   2. nearest category up the product's deepest path that has the attribute set
     *   3. the store-wide default
     */
    private function resolveGoogleCategory(Product $product, int $storeId): string
    {
        $override = trim($this->readScalar($product, AddGoogleCategoryAttributes::ATTR_CODE));
        if ($override !== '') {
            return $override;
        }
        $fromCategory = $this->resolveCategoryGoogleValue($product, $storeId);
        if ($fromCategory !== '') {
            return $fromCategory;
        }
        return trim($this->config->getDefaultGoogleCategory($storeId));
    }

    /**
     * Walk the product's deepest category path from leaf to root and return the
     * first dlabsit_google_category value that is set (inheritance up the tree).
     */
    private function resolveCategoryGoogleValue(Product $product, int $storeId): string
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        // Pick the deepest assigned category as the anchor.
        $anchor = null;
        $maxDepth = -1;
        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addAttributeToSelect(['path', AddGoogleCategoryAttributes::ATTR_CODE]);
            $collection->addFieldToFilter('entity_id', ['in' => $categoryIds]);
            foreach ($collection as $cat) {
                $depth = substr_count((string) $cat->getPath(), '/');
                if ($depth > $maxDepth) {
                    $maxDepth = $depth;
                    $anchor = $cat;
                }
            }
            if ($anchor === null) {
                return '';
            }

            $pathIds = array_values(array_filter(
                explode('/', (string) $anchor->getPath()),
                static fn ($id) => $id !== ''
            ));
            $byId = [];
            $pathColl = $this->categoryCollectionFactory->create();
            $pathColl->setStoreId($storeId);
            $pathColl->addAttributeToSelect(AddGoogleCategoryAttributes::ATTR_CODE);
            $pathColl->addFieldToFilter('entity_id', ['in' => $pathIds]);
            foreach ($pathColl as $cat) {
                $byId[(int) $cat->getId()] = trim((string) $cat->getData(AddGoogleCategoryAttributes::ATTR_CODE));
            }
            // Leaf → root.
            foreach (array_reverse($pathIds) as $id) {
                $value = $byId[(int) $id] ?? '';
                if ($value !== '') {
                    return $value;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Google category resolve failed', ['error' => $e->getMessage()]);
        }
        return '';
    }

    private function applyDimensions(Item $item, Product $product, int $storeId): void
    {
        $item->productLength = $this->readDimension($product, $this->config->getLengthAttribute($storeId));
        $item->productWidth = $this->readDimension($product, $this->config->getWidthAttribute($storeId));
        $item->productHeight = $this->readDimension($product, $this->config->getHeightAttribute($storeId));
    }

    /** A Google dimension value like "12.5 cm", or null when the attribute is unset/zero. */
    private function readDimension(Product $product, string $attrCode): ?string
    {
        if ($attrCode === '') {
            return null;
        }
        $raw = (float) $this->readScalar($product, $attrCode);
        if ($raw <= 0) {
            return null;
        }
        $value = rtrim(rtrim(number_format($raw, 2, '.', ''), '0'), '.');
        return $value . ' cm';
    }

    private function applyCustomLabels(Item $item, Product $product, int $storeId): void
    {
        for ($i = 0; $i < \Dlabsit\GoogleFeed\Helper\Config::CUSTOM_LABEL_COUNT; $i++) {
            $attrCode = $this->config->getCustomLabelAttribute($i, $storeId);
            if ($attrCode === '') {
                continue;
            }
            $value = trim($this->readOption($product, $attrCode));
            if ($value !== '') {
                $item->customLabels[$i] = $this->spec->truncate($value, 100);
            }
        }
    }

    private function applyProductDetails(Item $item, Product $product, int $storeId): void
    {
        foreach ($this->config->getDetailAttributes($storeId) as $attrCode) {
            $value = trim($this->readOption($product, $attrCode));
            if ($value === '') {
                continue;
            }
            $item->productDetails[] = [
                'section' => 'Specificaties',
                'name' => $this->spec->truncate($this->attributeLabel($product, $attrCode), 140),
                'value' => $this->spec->truncate($value, 1000),
            ];
        }
    }

    private function applyHighlights(Item $item, Product $product, int $storeId): void
    {
        $attrCode = $this->config->getHighlightAttribute($storeId);
        if ($attrCode === '') {
            return;
        }
        $raw = strip_tags($this->readScalar($product, $attrCode));
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $item->productHighlights[] = $this->spec->truncate($line, 150);
            }
        }
    }

    private function attributeLabel(Product $product, string $attrCode): string
    {
        $attribute = $product->getResource()->getAttribute($attrCode);
        if ($attribute && $attribute->getId()) {
            $label = (string) $attribute->getDefaultFrontendLabel();
            if ($label !== '') {
                return $label;
            }
        }
        return ucfirst(str_replace('_', ' ', $attrCode));
    }

    /** "start/end" ISO-8601 window, only when BOTH special from and to dates are set. */
    private function resolveSaleWindow(Product $product): ?string
    {
        $from = $product->getSpecialFromDate();
        $to = $product->getSpecialToDate();
        if (!$from || !$to) {
            return null;
        }
        $fromTs = strtotime((string) $from);
        $toTs = strtotime((string) $to);
        if ($fromTs === false || $toTs === false) {
            return null;
        }
        return gmdate('Y-m-d\TH:i:sP', $fromTs) . '/' . gmdate('Y-m-d\TH:i:sP', $toTs);
    }

    private function applyVariantAttributes(Item $item, Product $product, int $storeId): void
    {
        $color = trim($this->readOption($product, $this->config->getColorAttribute($storeId)));
        if ($color !== '') {
            $item->color = $this->spec->truncate($color, Spec::LIMIT_COLOR);
        }

        $size = trim($this->readOption($product, $this->config->getSizeAttribute($storeId)));
        if ($size !== '') {
            $item->size = $this->spec->truncate($size, Spec::LIMIT_SIZE);
        }

        $gender = $this->config->getDefaultGender($storeId);
        if ($this->spec->isValidGender($gender)) {
            $item->gender = $gender;
        }

        $ageGroup = $this->config->getDefaultAgeGroup($storeId);
        if ($this->spec->isValidAgeGroup($ageGroup)) {
            $item->ageGroup = $ageGroup;
        }
    }

    private function applyShipping(
        Item $item,
        Product $product,
        ?Product $parent,
        int $storeId,
        string $currency
    ): void {
        if ($this->config->includeShippingWeight($storeId)) {
            $grams = $this->getWeightGrams($product, $storeId);
            if ($grams === 0 && $parent !== null) {
                $grams = $this->getWeightGrams($parent, $storeId);
            }
            $item->shippingWeight = $this->spec->formatWeightKg($grams);
        }

        $country = trim($this->config->getShippingCountry($storeId));
        $price = $this->config->getShippingPrice($storeId);
        if ($country !== '' && $price !== null) {
            $item->shipping = [
                'country' => $country,
                'service' => trim($this->config->getShippingService($storeId)),
                'price' => $this->spec->formatPrice($price, $currency),
            ];
        }
    }

    private function resolveAvailability(Product $product, int $storeId): string
    {
        if ($this->isInStock($product) && $this->getStockQty($product) > 0) {
            return Spec::AVAILABILITY_IN_STOCK;
        }
        return Spec::AVAILABILITY_OUT_OF_STOCK;
    }

    private function resolveCondition(int $storeId): string
    {
        $condition = $this->config->getCondition($storeId);
        return $this->spec->isValidCondition($condition) ? $condition : 'new';
    }

    // --- Magento value readers (safe against missing attributes / arrays) ---

    private function readScalar(Product $product, string $attrCode): string
    {
        if ($attrCode === '') {
            return '';
        }
        $value = $product->getData($attrCode);
        if ($value === null) {
            return '';
        }
        if (is_array($value)) {
            return implode(',', array_filter($value, static fn ($v) => $v !== null && $v !== ''));
        }
        return (string) $value;
    }

    private function readOption(Product $product, string $attrCode): string
    {
        if ($attrCode === '') {
            return '';
        }
        $attribute = $product->getResource()->getAttribute($attrCode);
        if (!$attribute || !$attribute->getId()) {
            return '';
        }
        if ($attribute->usesSource()) {
            $value = $product->getAttributeText($attrCode);
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, static fn ($v) => $v !== null && $v !== ''));
            }
            return (string) $value;
        }
        return $this->readScalar($product, $attrCode);
    }

    private function resolveDescription(Product $product, int $storeId): string
    {
        $source = $this->config->getDescriptionSource($storeId);
        if ($source === DescriptionSource::SHORT_AND_LONG) {
            $parts = array_filter([
                trim($this->readScalar($product, 'short_description')),
                trim($this->readScalar($product, 'description')),
            ], static fn (string $p): bool => $p !== '');
            $raw = implode("\n\n", $parts);
        } else {
            $raw = $this->readScalar($product, $source);
        }
        $raw = strip_tags($raw);
        $raw = preg_replace('/\s+/', ' ', $raw) ?? '';
        return trim($raw);
    }

    private function getImageUrl(Product $product): string
    {
        $image = $product->getImage();
        if ($image && $image !== 'no_selection') {
            try {
                $baseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
                return $baseUrl . 'catalog/product' . $image;
            } catch (\Exception $e) {
                $this->logger->warning('Image URL failed for product ' . $product->getId());
            }
        }
        return '';
    }

    /**
     * @return string[]
     */
    private function getAdditionalImages(Product $product): array
    {
        $images = [];
        $gallery = $product->getMediaGalleryImages();
        if ($gallery === null) {
            return $images;
        }
        foreach ($gallery as $img) {
            if (count($images) >= Spec::MAX_ADDITIONAL_IMAGES) {
                break;
            }
            $url = (string) $img->getUrl();
            if ($url !== '' && $img->getFile() !== $product->getImage()) {
                $images[] = $url;
            }
        }
        return $images;
    }

    private function getCategoryPath(Product $product, int $storeId): string
    {
        $categoryIds = $product->getCategoryIds();
        if (empty($categoryIds)) {
            return '';
        }

        $deepest = '';
        $maxDepth = -1;
        foreach ($categoryIds as $categoryId) {
            $path = $this->resolveCategoryPath((int) $categoryId, $storeId);
            $depth = substr_count($path, ' > ');
            if ($path !== '' && $depth > $maxDepth) {
                $maxDepth = $depth;
                $deepest = $path;
            }
        }
        return $deepest;
    }

    private function resolveCategoryPath(int $categoryId, int $storeId): string
    {
        $key = $categoryId . '-' . $storeId;
        if (isset($this->categoryPathCache[$key])) {
            return $this->categoryPathCache[$key];
        }

        try {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addAttributeToSelect('name');
            $collection->addFieldToFilter('entity_id', $categoryId);
            $category = $collection->getFirstItem();
            if (!$category->getId()) {
                return $this->categoryPathCache[$key] = '';
            }

            // Magento path is "1/2/5/12"; 1 is the absolute root and the store's
            // root category is next. Keep everything UNDER the store root so the
            // full taxonomy depth reaches Google.
            $storeRootId = (int) $this->storeManager->getStore($storeId)->getRootCategoryId();
            $pathIds = array_values(array_filter(
                explode('/', (string) $category->getPath()),
                static fn ($id) => $id !== ''
            ));
            $rootCutIndex = array_search((string) $storeRootId, $pathIds, true);
            if ($rootCutIndex !== false) {
                $pathIds = array_slice($pathIds, $rootCutIndex + 1);
            } else {
                $pathIds = array_values(array_filter($pathIds, static fn ($id) => (int) $id !== 1));
            }
            if (empty($pathIds)) {
                return $this->categoryPathCache[$key] = (string) $category->getName();
            }

            $pathColl = $this->categoryCollectionFactory->create();
            $pathColl->setStoreId($storeId);
            $pathColl->addAttributeToSelect('name');
            $pathColl->addFieldToFilter('entity_id', ['in' => $pathIds]);
            $byId = [];
            foreach ($pathColl as $cat) {
                $byId[(int) $cat->getId()] = (string) $cat->getName();
            }

            $names = [];
            foreach ($pathIds as $pathId) {
                $name = $byId[(int) $pathId] ?? null;
                if ($name !== null && $name !== '') {
                    $names[] = $name;
                }
            }
            return $this->categoryPathCache[$key] = implode(' > ', $names);
        } catch (\Exception $e) {
            $this->logger->error('Category path failed', ['category_id' => $categoryId, 'error' => $e->getMessage()]);
            return $this->categoryPathCache[$key] = '';
        }
    }

    private function getFinalPrice(Product $product): float
    {
        return (float) $product->getFinalPrice();
    }

    private function getSpecialPrice(Product $product): ?float
    {
        $special = $product->getSpecialPrice();
        if ($special === null || $special === '' || (float) $special <= 0) {
            return null;
        }
        $now = time();
        $from = $product->getSpecialFromDate();
        $to = $product->getSpecialToDate();
        if ($from && strtotime((string) $from) > $now) {
            return null;
        }
        if ($to && strtotime((string) $to) < $now) {
            return null;
        }
        return (float) $special;
    }

    private function getWeightGrams(Product $product, int $storeId): int
    {
        $attrCode = $this->config->getWeightAttribute($storeId);
        $w = (float) $product->getData($attrCode);
        if ($w <= 0) {
            try {
                $raw = $product->getResource()->getAttributeRawValue((int) $product->getId(), $attrCode, $storeId);
                $w = (float) (is_array($raw) ? ($raw[$attrCode] ?? 0) : $raw);
            } catch (\Exception $e) {
                return 0;
            }
        }
        return $w > 0 ? (int) round($w * 1000) : 0;
    }

    private function getStockQty(Product $product): int
    {
        if ($product->getData('qty') !== null) {
            return max(0, (int) $product->getData('qty'));
        }
        $stockItem = $product->getExtensionAttributes()?->getStockItem();
        return $stockItem ? max(0, (int) $stockItem->getQty()) : 0;
    }

    private function isInStock(Product $product): bool
    {
        if ($product->getData('is_in_stock') !== null) {
            return (bool) $product->getData('is_in_stock');
        }
        $stockItem = $product->getExtensionAttributes()?->getStockItem();
        return $stockItem ? (bool) $stockItem->getIsInStock() : false;
    }

    public function clearCache(): void
    {
        $this->categoryPathCache = [];
    }
}
