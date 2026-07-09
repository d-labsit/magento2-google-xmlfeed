<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Config\Source;

use Dlabsit\GoogleFeed\Model\Google\Spec;
use Magento\Framework\Data\OptionSourceInterface;

class AgeGroup implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('-- Not set --')]];
        foreach (Spec::AGE_GROUP_VALUES as $value) {
            $options[] = ['value' => $value, 'label' => ucwords(str_replace('_', ' ', $value))];
        }
        return $options;
    }
}
