<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use RuntimeException;

final class DuplicateBlockCodeException extends RuntimeException
{
    public static function forCode(string $code, string $existingClass, string $incomingClass): self
    {
        return new self(sprintf(
            'Block code [%s] is already registered by [%s]; refusing to register [%s].',
            $code,
            $existingClass,
            $incomingClass,
        ));
    }
}
