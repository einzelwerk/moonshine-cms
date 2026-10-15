<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Exceptions;

use Ewk\Cms\Pages\Support\PageLimits;
use RuntimeException;

/**
 * The structural refusals of a move. They all happen before anything is
 * written, inside the same transaction.
 */
final class InvalidPagePlacementException extends RuntimeException
{
    public static function intoItself(string $path): self
    {
        return new self(sprintf('Page [%s] cannot become its own parent.', $path));
    }

    public static function intoDescendant(string $path, string $parentPath): self
    {
        return new self(sprintf('Page [%s] cannot be moved into its own descendant [%s].', $path, $parentPath));
    }

    public static function depthExceeded(string $path, int $depth): self
    {
        return new self(sprintf(
            'Moving [%s] would put a page at level %d; the tree holds %d levels.',
            $path,
            $depth + 1,
            PageLimits::DEPTH_LEVELS,
        ));
    }

    public static function pathTooLong(string $path): self
    {
        return new self(sprintf(
            'Path is %d characters long, the limit is %d: %s',
            mb_strlen($path),
            PageLimits::PATH_LENGTH,
            mb_substr($path, 0, 80),
        ));
    }

    public static function underTemplate(string $parentPath): self
    {
        return new self(sprintf(
            'Page cannot hang under template page [%s]: a template is not an address of the tree.',
            $parentPath,
        ));
    }

    public static function fixedPath(string $type): self
    {
        return new self(sprintf(
            'Page of type [%s] takes its address from the type declaration, not from the tree, and cannot be moved.',
            $type,
        ));
    }
}
