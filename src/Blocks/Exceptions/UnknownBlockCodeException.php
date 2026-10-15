<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use RuntimeException;

final class UnknownBlockCodeException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self(sprintf('Block code [%s] is not registered.', $code));
    }
}
