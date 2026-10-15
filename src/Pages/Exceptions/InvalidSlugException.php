<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use Ewk\Cms\Pages\Support\PageLimits;
use RuntimeException;

final class InvalidSlugException extends RuntimeException
{
    public static function notKebab(string $slug): self
    {
        return new self(sprintf(
            'Slug [%s] is not a single kebab-case segment; a slug never carries slashes, capitals or spaces.',
            $slug,
        ));
    }

    public static function tooLong(string $slug): self
    {
        return new self(sprintf(
            'Slug [%s] is longer than %d characters.',
            mb_substr($slug, 0, 40),
            PageLimits::SLUG_LENGTH,
        ));
    }

    public static function fromSource(string $source): self
    {
        return new self(sprintf('Nothing to build a slug from in [%s].', mb_substr($source, 0, 60)));
    }
}
