<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use RuntimeException;

/**
 * Deleting a shared block that is still inserted somewhere. Neither silent
 * emptiness nor a cascade over the insertions: the admin lists the usages and
 * offers to unlink them.
 */
final class SharedBlockInUseException extends RuntimeException
{
    public static function forSharedBlock(CmsSharedBlock $sharedBlock, int $insertions): self
    {
        return new self(
            "Shared block [{$sharedBlock->code}] cannot be deleted while it has {$insertions} insertion(s)."
        );
    }
}
