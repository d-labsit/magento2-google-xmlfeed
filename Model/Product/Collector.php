<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Product;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Setup\Patch\Data\AddGoogleCategoryAttributes;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

/**
 * Yields exportable products for a store, batched so memory stays flat
 * regardless of catalogue size.
 */
class Collector
{
    public function __construct(
        private readonly CollectionFactory $productCollectionFactory,
        private readonly Config $config,
        private readonly StockHelper $stockHelper
    ) {
    }

    /**
     * @return \Generator<int, Product>
     */
    public function collect(int $storeId): \Generator
    {
        $batchSize = $this->config->getBatchSize($storeId);
        $page = 1;

        while (true) {
            $collection = $this->createCollection($storeId);
            $collection->setPageSize($batchSize);
            $collection->setCurPage($page);

            if ($collection->getSize() === 0) {
                break;
            }

            // Must run AFTER pagination is set: addMediaGalleryData() forces the
            // collection to load, and if that happens before setPageSize() the
            // page limit is ignored and every page re-yields the whole catalogue.
            $collection->addUrlRewrite();
            $collection->addMediaGalleryData();

            foreach ($collection as $product) {
                yield $product;
            }

            if ($page >= $collection->getLastPageNumber()) {
                break;
            }
            $page++;
        }
    }

    /**
     * Enabled, in-stock (unless configured otherwise) simple children of a
     * configurable product.
     *
     * @return Product[]
     */
    public function getConfigurableChildren(Product $configurable, int $storeId): array
    {
        /** @var Configurable $type */
        $type = $configurable->getTypeInstance();
        $includeOos = $this->config->includeOutOfStock($storeId);

        $valid = [];
        foreach ($type->getUsedProducts($configurable) as $child) {
            if ((int) $child->getStatus() !== Status::STATUS_ENABLED) {
                continue;
            }
            if (!$includeOos) {
                $stockItem = $child->getExtensionAttributes()?->getStockItem();
                if ($stockItem && !$stockItem->getIsInStock()) {
                    continue;
                }
            }
            $valid[] = $child;
        }
        return $valid;
    }

    private function createCollection(int $storeId): Collection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);

        // Optional mapping attributes are per-shop — drop empty codes and dedupe
        // so a disabled mapping never selects an empty or duplicate attribute.
        $mapped = [
            $this->config->getBrandAttribute($storeId),
            $this->config->getGtinAttribute($storeId),
            $this->config->getMpnAttribute($storeId),
            $this->config->getColorAttribute($storeId),
            $this->config->getSizeAttribute($storeId),
            $this->config->getWeightAttribute($storeId),
            $this->config->getLengthAttribute($storeId),
            $this->config->getWidthAttribute($storeId),
            $this->config->getHeightAttribute($storeId),
            $this->config->getHighlightAttribute($storeId),
            AddGoogleCategoryAttributes::ATTR_CODE,
        ];
        for ($i = 0; $i < Config::CUSTOM_LABEL_COUNT; $i++) {
            $mapped[] = $this->config->getCustomLabelAttribute($i, $storeId);
        }
        $mapped = array_merge($mapped, $this->config->getDetailAttributes($storeId));

        $attributes = array_values(array_unique(array_filter(array_merge([
            'name', 'url_key', 'price', 'special_price',
            'special_from_date', 'special_to_date',
            'image', 'small_image', 'description', 'short_description',
            'meta_description', 'weight', 'status', 'visibility',
        ], $mapped), static fn (string $code): bool => $code !== '')));

        $collection->addAttributeToSelect($attributes);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]]);
        $collection->addAttributeToFilter('type_id', ['in' => [Type::TYPE_SIMPLE, Configurable::TYPE_CODE]]);

        if (!$this->config->includeOutOfStock($storeId)) {
            $this->stockHelper->addInStockFilterToCollection($collection);
        }

        $collection->joinField(
            'qty',
            'cataloginventory_stock_item',
            'qty',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );
        $collection->joinField(
            'is_in_stock',
            'cataloginventory_stock_item',
            'is_in_stock',
            'product_id=entity_id',
            '{{table}}.stock_id=1',
            'left'
        );

        return $collection;
    }
}
