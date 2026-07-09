<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Controller\Adminhtml\Generate;

use Dlabsit\GoogleFeed\Helper\Config;
use Dlabsit\GoogleFeed\Model\FeedGenerator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Admin AJAX endpoint behind the "Generate Now" config button.
 */
class Index extends Action
{
    public const ADMIN_RESOURCE = 'Dlabsit_GoogleFeed::config';

    public function __construct(
        Context $context,
        private readonly JsonFactory $resultJsonFactory,
        private readonly FeedGenerator $generator,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();
        $storeParam = (int) $this->getRequest()->getParam('store', 0);

        try {
            $storeIds = $this->resolveStoreIds($storeParam);
            if ($storeIds === []) {
                return $result->setData([
                    'success' => false,
                    'message' => (string) __('No stores with the feed enabled. Enable it and save first.'),
                ]);
            }

            $parts = [];
            foreach ($storeIds as $storeId) {
                $run = $this->generator->generate($storeId);
                $code = $this->storeManager->getStore($storeId)->getCode();
                $parts[] = sprintf('%s: %d items', $code, $run->itemCount);
            }
            return $result->setData([
                'success' => true,
                'message' => (string) __('Done — %1', implode(' · ', $parts)),
            ]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * A specific store when one is scoped, else every store with the feed enabled.
     *
     * @return int[]
     */
    private function resolveStoreIds(int $storeParam): array
    {
        if ($storeParam > 0) {
            return [$storeParam];
        }
        $ids = [];
        foreach ($this->storeManager->getStores() as $store) {
            $id = (int) $store->getId();
            if ($this->config->isEnabled($id)) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
