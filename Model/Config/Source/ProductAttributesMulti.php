<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Config\Source;

use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Multi-select product-attribute picker (no empty option) — used for the
 * product_detail attribute list.
 */
class ProductAttributesMulti implements OptionSourceInterface
{
    private const EXCLUDED_CODES = ['old_id'];

    public function __construct(
        private readonly CollectionFactory $attributeCollectionFactory
    ) {
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        $collection = $this->attributeCollectionFactory->create();
        $collection->addFieldToFilter('frontend_input', ['neq' => 'hidden']);
        $collection->addFieldToFilter('attribute_code', ['nin' => self::EXCLUDED_CODES]);
        $collection->setOrder('frontend_label', 'ASC');

        foreach ($collection as $attribute) {
            $code = (string) $attribute->getAttributeCode();
            $label = (string) $attribute->getFrontendLabel();
            $display = $label !== '' ? $label : ucfirst(str_replace('_', ' ', $code));
            $options[] = ['value' => $code, 'label' => $display . ' (' . $code . ')'];
        }
        return $options;
    }
}
