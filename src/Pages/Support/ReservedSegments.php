<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Support;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Contracts\Config\Repository;

/**
 * Addresses a page may not take. The list is made of two parts: the configured
 * prefixes of the package and of the frontend, and the language codes of the
 * catalog.
 *
 * The two parts are kept apart on purpose. A configured prefix is a conflict
 * with a route that always wins, so the page is refused at save time. A
 * language code in the address is an integration mistake — the language travels
 * in `Accept-Language` — and the resolver reports it as such instead of hiding
 * it behind a 404.
 */
final readonly class ReservedSegments
{
    public function __construct(
        private Repository $config,
        private LanguageCatalog $languages,
    ) {
    }

    /**
     * The configured prefix this path collides with, or null when it is free.
     * Entries are matched as prefixes: `auth` rejects both `/auth` and
     * `/auth/login`, and never `/authors`.
     */
    public function conflict(string $path): ?string
    {
        $normalized = mb_strtolower(PathNormalizer::normalize($path));

        foreach ($this->entries() as $entry) {
            if ($normalized === $entry || PathNormalizer::isDescendantOf($normalized, $entry)) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * A segment carrying a content language of the catalog, compared against
     * both the full code and its primary subtag: a frontend prefixing `de-CH`
     * is the same mistake as one prefixing `de`.
     */
    public function isLanguageCode(string $segment): bool
    {
        $candidate = $this->canonicalLanguage($segment);

        if ($candidate === '') {
            return false;
        }

        foreach ($this->languages->locales() as $locale) {
            $known = $this->canonicalLanguage($locale);

            if ($candidate === $known || $candidate === explode('-', $known)[0]) {
                return true;
            }
        }

        return false;
    }

    /**
     * Configured prefixes as normalized, lower cased paths.
     *
     * @return list<string>
     */
    public function entries(): array
    {
        $configured = $this->config->get('cms-pages.reserved_segments', []);

        if (! is_array($configured)) {
            return [];
        }

        $entries = [];

        foreach ($configured as $entry) {
            if (! is_string($entry) || trim($entry) === '') {
                continue;
            }

            $normalized = mb_strtolower(PathNormalizer::normalize($entry));

            // An entry collapsing to the root would reserve the whole site,
            // which is never what an editor of this list meant.
            if ($normalized === PathNormalizer::ROOT) {
                continue;
            }

            $entries[] = $normalized;
        }

        return array_values(array_unique($entries));
    }

    private function canonicalLanguage(string $code): string
    {
        return mb_strtolower(str_replace('_', '-', trim($code)));
    }
}
