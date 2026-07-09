<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Cron;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Logger\Logger;
use Dlabsit\GoogleFeed\Model\FeedGenerator;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Scheduled feed generation for every store view where the feed is enabled.
 * Runs on the crontab expression from dlabsit_googlefeed/general/cron_schedule.
 */
class Generate
{
    public function __construct(
        private readonly FeedGenerator $generator,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly Emulation $emulation,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            if (!$this->config->isEnabled($storeId)) {
                continue;
            }
            // Emulate the store frontend so product URLs and media resolve correctly.
            $this->emulation->startEnvironmentEmulation($storeId, 'frontend', true);
            try {
                $result = $this->generator->generate($storeId);
                $this->logger->info(sprintf(
                    'Cron: generated Google feed for store %s (%d items)',
                    $store->getCode(),
                    $result->itemCount
                ));
            } catch (\Exception $e) {
                $this->logger->error(
                    'Cron: Google feed failed for store ' . $store->getCode(),
                    ['exception' => $e->getMessage()]
                );
            } finally {
                $this->emulation->stopEnvironmentEmulation();
            }
        }
    }
}
