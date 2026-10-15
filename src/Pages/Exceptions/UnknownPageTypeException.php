<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use RuntimeException;

final class UnknownPageTypeException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Page type [%s] is not registered.', $key));
    }
}
