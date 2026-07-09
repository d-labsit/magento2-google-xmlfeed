<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   MIT — see LICENSE.md
 */

declare(strict_types=1);

namespace Dlabsit\GoogleFeed\Model;

/**
 * Outcome of a single feed generation run.
 */
class GenerationResult
{
    public function __construct(
        public readonly string $absolutePath,
        public readonly string $publicUrl,
        public readonly int $itemCount
    ) {
    }
}
