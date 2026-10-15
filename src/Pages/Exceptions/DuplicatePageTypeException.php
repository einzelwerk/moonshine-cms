<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use RuntimeException;

final class DuplicatePageTypeException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self(sprintf('Page type [%s] is already registered; refusing to redeclare it.', $key));
    }
}
