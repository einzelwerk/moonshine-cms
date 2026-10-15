<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use RuntimeException;

/**
 * Publication is the place where integrity is checked, because a draft must
 * save in any state. A failed publication returns the list of what is in the
 * way and writes nothing.
 */
final class PublicationRejectedException extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    private function __construct(public readonly array $problems)
    {
        parent::__construct('Publication rejected: '.implode('; ', $problems).'.');
    }

    /**
     * @param  list<string>  $problems
     */
    public static function withProblems(array $problems): self
    {
        return new self($problems);
    }
}
