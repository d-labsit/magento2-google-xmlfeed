<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Logger\Logger;
use Dlabsit\GoogleFeed\Model\Google\FeedWriter;
use Dlabsit\GoogleFeed\Model\Product\Collector;
use Dlabsit\GoogleFeed\Model\Product\Mapper;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Orchestrates one feed run for a store view: collect products, map each to a
 * Google item (variants expanded, sharing item_group_id), stream to a temp
 * file, then atomically move it into place so no half-written feed is served.
 */
class FeedGenerator
{
    private const OUTPUT_DIR = 'googlefeed';

    public function __construct(
        private readonly Config $config,
        private readonly Collector $collector,
        private readonly Mapper $mapper,
        private readonly FeedWriter $writer,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly Logger $logger
    ) {
    }

    public function generate(int $storeId): GenerationResult
    {
        $store = $this->storeManager->getStore($storeId);
        // Products/URLs must resolve in the target store's scope.
        $this->storeManager->setCurrentStore($storeId);

        $currency = (string) $store->getCurrentCurrencyCode();
        $storeName = $this->config->getStoreName($storeId) ?: (string) $store->getName();
        $storeUrl = (string) $store->getBaseUrl();

        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $relDir = self::OUTPUT_DIR . '/' . $store->getCode();
        $mediaDir->create($relDir);

        $filename = $this->config->getFilename($storeId);
        $relPath = $relDir . '/' . $filename;
        $tmpRelPath = $relPath . '.tmp';
        $absTmp = $mediaDir->getAbsolutePath($tmpRelPath);

        $this->mapper->clearCache();
        $this->writer->open($absTmp, $storeName, $storeUrl, $storeName . ' — Google Shopping feed');

        $count = 0;
        foreach ($this->collector->collect($storeId) as $product) {
            try {
                $count += $this->writeProduct($product, $storeId, $currency);
            } catch (\Exception $e) {
                // One broken product must never abort the whole feed.
                $this->logger->error(
                    'Google feed: product ' . $product->getId() . ' failed',
                    ['exception' => $e->getMessage()]
                );
            }
        }

        $this->writer->close();
        $mediaDir->renameFile($tmpRelPath, $relPath);

        $publicUrl = rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/') . '/' . $relPath;
        $this->logger->info(sprintf('Google feed generated: %s (%d items)', $relPath, $count));

        return new GenerationResult($mediaDir->getAbsolutePath($relPath), $publicUrl, $count);
    }

    /**
     * Write one catalogue product; a configurable expands into one item per
     * enabled child variant, all sharing the configurable's item_group_id.
     *
     * @return int number of <item> elements written
     */
    private function writeProduct(Product $product, int $storeId, string $currency): int
    {
        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $children = $this->collector->getConfigurableChildren($product, $storeId);
            if ($children === []) {
                return 0;
            }
            $groupId = $this->mapper->getItemGroupId($product, $storeId);
            $written = 0;
            foreach ($children as $child) {
                $this->writer->writeItem($this->mapper->map($child, $storeId, $currency, $product, $groupId));
                $written++;
            }
            return $written;
        }

        $this->writer->writeItem($this->mapper->map($product, $storeId, $currency));
        return 1;
    }
}
