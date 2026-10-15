<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Exceptions;

use RuntimeException;

/**
 * A menu was put into a slot the frontend has no place for. The dictionary is
 * closed on purpose, so an unknown code is a mistake at write time and not an
 * empty area at render time.
 */
final class UnknownMenuSlotException extends RuntimeException
{
    /**
     * @param  list<string>  $known
     */
    public static function forCode(string $code, array $known): self
    {
        return new self(sprintf(
            'Unknown menu slot [%s]. Known slots: %s. Declare a new one in `cms-layouts.menu_slots`.',
            $code,
            implode(', ', $known),
        ));
    }
}
