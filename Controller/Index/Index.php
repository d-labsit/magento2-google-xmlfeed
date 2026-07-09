<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Controller\Index;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Model\FeedGenerator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Serves the generated Google feed for the current store view at a stable URL:
 * https://<store>/googlefeed
 *
 * The file is generated on demand if it does not exist yet, so a fresh store
 * never returns an empty response.
 */
class Index implements HttpGetActionInterface
{
    private const OUTPUT_DIR = 'googlefeed';

    public function __construct(
        private readonly RawFactory $rawFactory,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly FeedGenerator $generator
    ) {
    }

    public function execute(): Raw
    {
        $raw = $this->rawFactory->create();
        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();
        $relPath = self::OUTPUT_DIR . '/' . $store->getCode() . '/' . $this->config->getFilename($storeId);

        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        if (!$mediaDir->isExist($relPath)) {
            $this->generator->generate($storeId);
        }

        if (!$mediaDir->isExist($relPath)) {
            return $raw->setHttpResponseCode(HttpResponse::STATUS_CODE_404)
                ->setContents('Feed not available.');
        }

        return $raw
            ->setHeader('Content-Type', 'application/xml; charset=utf-8')
            ->setContents($mediaDir->readFile($relPath));
    }
}
