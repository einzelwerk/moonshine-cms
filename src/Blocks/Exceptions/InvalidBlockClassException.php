<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use Ewk\Cms\Blocks\Contracts\BlockContract;
use InvalidArgumentException;

final class InvalidBlockClassException extends InvalidArgumentException
{
    public static function forClass(string $blockClass): self
    {
        return new self(sprintf(
            'Block class [%s] must implement [%s].',
            $blockClass,
            BlockContract::class,
        ));
    }
}
