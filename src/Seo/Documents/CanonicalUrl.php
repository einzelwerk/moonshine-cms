<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Documents;

use Ewk\Cms\Blocks\Params\PageQuery;
use Ewk\Cms\Seo\Support\SiteUrl;

/**
 * The canonical address. Three rules: it points at itself including the page
 * number of pagination, because the documentation of the search engine demands
 * exactly that; filtering parameters are excluded; the order of parameters is
 * fixed and the separator is `&`.
 *
 * We can do this — and the frontend cannot — because the parameters of the
 * blocks arrive at the resolver, and which of them filter is derived from the
 * schemas of the blocks of the page.
 */
final readonly class CanonicalUrl
{
    public function __construct(private SiteUrl $siteUrl)
    {
    }

    /**
     * A non-empty `$override` wins: a manual canonical address is a field for
     * exceptions, and an exception the package recomputes is not an exception.
     *
     * An address with a host of its own is taken as it stands — the editor is
     * pointing at another site. Anything else is a path on this site and goes
     * through {@see SiteUrl}: a relative address in a canonical tag is simply
     * wrong, and an editor typing `/afisha` means the page, not the string.
     */
    public function build(SeoContext $context, ?string $override = null): string
    {
        if ($override !== null && trim($override) !== '') {
            $override = trim($override);

            return self::hasHost($override)
                ? $override
                : $this->siteUrl->absolute($override, $context->locale);
        }

        return $this->forLocale($context, $context->locale);
    }

    /**
     * `https://site/page`, and the protocol-relative `//site/page` with it.
     */
    private static function hasHost(string $address): bool
    {
        return preg_match('#^([a-z][a-z0-9+.\-]*:)?//#i', $address) === 1;
    }

    /**
     * The same address on another language. The path of a page is one for all
     * languages — the slug is not translatable in the first version — so a
     * language version differs by the prefix and by nothing else.
     */
    public function forLocale(SeoContext $context, string $locale): string
    {
        $url = $this->siteUrl->absolute($context->path, $locale);
        $query = $this->query($context);

        return $query === '' ? $url : $url.'?'.$query;
    }

    /**
     * The query string of the canonical address: positional parameters of
     * pagination stay, filtering ones are dropped, keys are sorted so two
     * requests differing only in the order of parameters produce one address.
     */
    public function query(SeoContext $context): string
    {
        // The one encoder of the CMS, see PageQuery::encode(): the `self`
        // link of pagination has to be the canonical address character for
        // character, and two encoders would differ over the brackets of a
        // nested key sooner or later.
        return PageQuery::encode(self::withoutKeys($context->query, $context->filterKeys));
    }

    /**
     * @param  array<array-key, mixed>  $query
     * @param  list<string>  $keys
     * @return array<array-key, mixed>
     */
    private static function withoutKeys(array $query, array $keys): array
    {
        foreach ($keys as $key) {
            $segments = self::segments($key);

            if ($segments === []) {
                continue;
            }

            self::forget($query, $segments);
        }

        return $query;
    }

    /**
     * `b[afisha][city]` is three steps into a nested array. A name that does
     * not parse is ignored rather than guessed at: dropping the wrong
     * parameter would silently change the canonical address.
     *
     * @return list<string>
     */
    private static function segments(string $key): array
    {
        if (preg_match('/^([^\[\]]+)((?:\[[^\[\]]+\])*)$/', $key, $matches) !== 1) {
            return [];
        }

        $segments = [$matches[1]];

        if ($matches[2] !== '' && preg_match_all('/\[([^\[\]]+)\]/', $matches[2], $inner) >= 1) {
            foreach ($inner[1] as $segment) {
                $segments[] = $segment;
            }
        }

        return $segments;
    }

    /**
     * A parent left empty by the removal goes too: `?b=` in a canonical
     * address is a parameter that means nothing.
     *
     * @param  array<array-key, mixed>  $query
     * @param  list<string>  $segments
     */
    private static function forget(array &$query, array $segments): void
    {
        $head = array_shift($segments);

        if ($head === null || ! array_key_exists($head, $query)) {
            return;
        }

        if ($segments === []) {
            unset($query[$head]);

            return;
        }

        $child = $query[$head];

        if (! is_array($child)) {
            return;
        }

        self::forget($child, $segments);

        if ($child === []) {
            unset($query[$head]);

            return;
        }

        $query[$head] = $child;
    }
}
