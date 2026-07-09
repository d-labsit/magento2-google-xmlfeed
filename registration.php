<?php
/**
 * Dlabsit Google Feed Module for Magento 2
 *
 * Generates a Google Merchant Center (Google Shopping) product feed.
 *
 * @category   Dlabsit
 * @package    Dlabsit_GoogleFeed
 * @copyright  Copyright (c) 2026 Dlabsit (https://github.com/dlabsit)
 * @license    MIT — see LICENSE.md
 */

declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Dlabsit_GoogleFeed', __DIR__);
