<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Exceptions;

use InvalidArgumentException;

final class UnknownMenuSourceException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $known
     */
    public static function forCode(string $code, array $known): self
    {
        return new self(sprintf(
            'Menu source [%s] is not registered; known sources: %s.',
            $code,
            $known === [] ? 'none' : implode(', ', $known),
        ));
    }
}
