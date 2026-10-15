<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

/**
 * One normalization for every address: the same function serves the resolver,
 * the storage of page paths and everything else that compares addresses. Two
 * copies of these rules would mean an address that is stored one way and looked
 * up another.
 *
 * Rules: a leading slash is mandatory, a trailing one is dropped, repeated
 * slashes collapse, percent encoding is expanded, case is preserved.
 *
 * The length limit is checked, never silently applied: a truncated path is a
 * different address, and cutting it would quietly resolve to the wrong page.
 */
final class PathNormalizer
{
    public const string ROOT = '/';

    public static function normalize(string $path): string
    {
        $segments = self::segments($path);

        return $segments === [] ? self::ROOT : self::ROOT.implode('/', $segments);
    }

    /**
     * The normalized path split into its segments; the root is an empty list.
     *
     * @return list<string>
     */
    public static function segments(string $path): array
    {
        $segments = [];

        foreach (explode('/', rawurldecode(trim($path))) as $segment) {
            $segment = trim($segment);

            if ($segment === '') {
                continue;
            }

            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * The address of a child: the parent path plus one segment. A root level
     * page has no parent path and hangs directly off the root.
     */
    public static function join(?string $parentPath, string $slug): string
    {
        return self::normalize(($parentPath ?? self::ROOT).self::ROOT.$slug);
    }

    public static function withinLimit(string $path): bool
    {
        return mb_strlen($path) <= PageLimits::PATH_LENGTH;
    }

    /**
     * Prefix comparison that cannot capture a sibling: `/concert` is not a
     * prefix of `/concert-hall`, because the separator is part of the test.
     */
    public static function isDescendantOf(string $path, string $ancestorPath): bool
    {
        $prefix = $ancestorPath === self::ROOT ? self::ROOT : $ancestorPath.self::ROOT;

        return $path !== $ancestorPath && str_starts_with($path, $prefix);
    }
}
