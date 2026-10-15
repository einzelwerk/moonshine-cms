<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use RuntimeException;

/**
 * Moving a block into its own subtree. The check belongs to the move endpoint
 * above all: drag and drop is the one place where the parent of a block changes
 * without a form.
 */
final class BlockCycleException extends RuntimeException
{
    public static function forBlock(string $uuid): self
    {
        return new self("Block [{$uuid}] cannot be moved into itself or into one of its own descendants.");
    }
}
