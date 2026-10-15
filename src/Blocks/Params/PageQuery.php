<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * The query string of the next state of a page. The backend builds these
 * addresses because only it knows both sides: the anchor namespace and the
 * subject of a template page. The frontend follows a ready address and
 * assembles nothing.
 *
 * The result is relative and holds the query string only, so the backend needs
 * to know neither the host of the frontend nor the path of the page, and the
 * parameters of the other blocks survive untouched.
 */
final class PageQuery
{
    /**
     * The state of the page with the set of one block overridden. A value of
     * `null` in the override removes the key, which is how the first page drops
     * its `page` parameter.
     *
     * @param  array<string, mixed>  $override
     * @return string `'?…'`, or a bare `'?'` when nothing is left to write
     */
    public static function with(ResolvedParameters $resolved, string $anchor, array $override = []): string
    {
        $query = $resolved->toQuery();

        /** @var array<string, array<string, mixed>> $sets */
        $sets = $query['b'] ?? [];
        $set = $sets[$anchor] ?? [];

        foreach ($override as $name => $value) {
            if ($value === null) {
                unset($set[$name]);

                continue;
            }

            $set[$name] = $value;
        }

        if ($set === []) {
            unset($sets[$anchor]);
        } else {
            $sets[$anchor] = $set;
        }

        // A bare `?` and not an empty string: an empty href in a document
        // points at the current address with the whole of its query string,
        // which is the state the link is supposed to leave behind.
        return '?'.self::encode(['b' => $sets]);
    }

    /**
     * The one encoder of the CMS, so every address of the CMS is written the
     * same way and one normalized state has exactly one string: keys sorted
     * recursively, the brackets of a nested key left readable, values escaped as
     * usual, `&` between pairs and no leading `?`.
     *
     * The brackets of the namespace stay raw because the address is copied,
     * pasted and lands in a sitemap, and every client accepts them. Inside a
     * value nothing is decoded — a bracket a visitor typed into a filter stays
     * escaped.
     *
     * @param  array<array-key, mixed>  $query
     */
    public static function encode(array $query): string
    {
        return implode('&', self::pairs($query, null));
    }

    /**
     * @param  array<array-key, mixed>  $query
     * @return list<string>
     */
    private static function pairs(array $query, ?string $prefix): array
    {
        // Sorted as strings, so the order does not depend on whether a key
        // happens to look like a number.
        ksort($query, SORT_STRING);

        $pairs = [];

        foreach ($query as $key => $value) {
            $name = $prefix === null
                ? rawurlencode((string) $key)
                : $prefix.'['.rawurlencode((string) $key).']';

            if (is_array($value)) {
                $pairs = [...$pairs, ...self::pairs($value, $name)];

                continue;
            }

            $pairs[] = $name.'='.rawurlencode(self::scalar($value));
        }

        return $pairs;
    }

    private static function scalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
