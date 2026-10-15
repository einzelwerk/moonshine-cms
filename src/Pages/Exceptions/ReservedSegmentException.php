<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use RuntimeException;

/**
 * A page whose address collides with a route of the frontend or carries a
 * language code is refused where the address is set: a silently lost conflict
 * is a page that simply does not open.
 */
final class ReservedSegmentException extends RuntimeException
{
    public static function forPath(string $path, string $reserved): self
    {
        return new self(sprintf('Path [%s] is reserved by [%s].', $path, $reserved));
    }

    public static function forLanguageCode(string $path, string $segment): self
    {
        return new self(sprintf(
            'Path [%s] starts with language code [%s]; the language travels in the Accept-Language header, not in the address.',
            $path,
            $segment,
        ));
    }
}
