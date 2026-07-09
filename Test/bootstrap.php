<?php
/**
 * PHPUnit bootstrap. The unit tests cover the pure-PHP spec classes (no Magento
 * dependency), so they run both inside a full Magento install (vendor/autoload)
 * and standalone in CI where only phpunit is installed.
 *
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

$base = dirname(__DIR__);

require $base . '/Model/Google/Spec.php';
require $base . '/Model/Google/Validator/Gtin.php';

$autoload = $base . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;
}
