<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use RuntimeException;

/**
 * Cardinality of a page type is an integrity rule and not a hint: exactly one
 * page for a singleton and exactly one active template per template type.
 */
final class PageTypeCardinalityException extends RuntimeException
{
    public static function secondSingleton(string $type): self
    {
        return new self(sprintf('Page type [%s] is a singleton and already has its page.', $type));
    }

    public static function secondTemplate(string $type): self
    {
        return new self(sprintf('Page type [%s] already has an active template; a second one cannot be created.', $type));
    }
}
