<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use RuntimeException;

final class PathCollisionException extends RuntimeException
{
    public static function forPath(string $path): self
    {
        return new self(sprintf('Path [%s] is already taken by another page.', $path));
    }
}
