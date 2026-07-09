<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class UniqueIdSource implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sku', 'label' => __('SKU')],
            ['value' => 'product_id', 'label' => __('Product ID (entity id)')],
            ['value' => 'custom', 'label' => __('Custom attribute')],
        ];
    }
}
