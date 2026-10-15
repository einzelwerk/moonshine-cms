<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

/**
 * The three limits of the page tree. They are constants and not configuration:
 * they follow from the index the unique path lives in, so an installation
 * cannot raise them by editing a file.
 *
 * Five levels of a hundred characters plus separators fit into the path.
 */
final class PageLimits
{
    public const int SLUG_LENGTH = 100;

    public const int PATH_LENGTH = 512;

    /** Levels, not the value of the column: a root page has depth 0. */
    public const int DEPTH_LEVELS = 5;

    public const int MAX_DEPTH = self::DEPTH_LEVELS - 1;
}
