<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class DescriptionSource implements OptionSourceInterface
{
    public const SHORT_AND_LONG = 'short_description_description';

    /**
     * @return array<int, array{value: string, label: \Magento\Framework\Phrase|string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'description', 'label' => __('Description')],
            ['value' => 'short_description', 'label' => __('Short description')],
            ['value' => 'meta_description', 'label' => __('Meta description')],
            ['value' => self::SHORT_AND_LONG, 'label' => __('Short description + Description')],
        ];
    }
}
