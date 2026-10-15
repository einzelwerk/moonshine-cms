<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Seo\Support\SiteUrl;
use Illuminate\Contracts\Config\Repository;

/**
 * What the frontend builds its sitemap from: a list of files and the entries of
 * one file. The entries are shaped after `MetadataRoute.Sitemap` of Next —
 * `url`, `lastModified`, `alternates.languages` — so the frontend returns them
 * as they arrive, the same way it hands the metadata object to
 * `generateMetadata`.
 *
 * **A file holds one language and one source**: `ru-event-2`. The languages
 * are split so that a search console reports the indexing of every language
 * on its own; the sources, so that it reports events apart from tours. Every
 * entry still names all its language versions, itself and `x-default`
 * included: the versions of one page do not have to share a file, they have
 * to name each other.
 *
 * **A single language** — a catalog of one, or addresses without a language
 * prefix, where all languages share one address — has nothing to split and
 * nothing to name: one entry per path, files named by the source alone,
 * `event-2`, and no `alternates`.
 *
 * The number of entries in a file is `cms-seo.sitemap.per_file`, never above
 * the 50 000 of the protocol.
 */
final readonly class SitemapFeed
{
    /** The ceiling of the protocol: entries of one file. */
    public const int MAX_PER_FILE = 50000;

    public const int DEFAULT_PER_FILE = 10000;

    public function __construct(
        private SitemapRegistry $registry,
        private LanguageCatalog $languages,
        private SiteUrl $siteUrl,
        private Repository $config,
    ) {
    }

    /**
     * The names of the files, in a stable order. A source with nothing to
     * list has no file at all.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $perFile = $this->perFile();
        $counted = [];

        foreach ($this->registry->sources() as $source) {
            $count = $source->count();

            if ($count > 0) {
                $counted[$source->key()] = (int) ceil($count / $perFile);
            }
        }

        $files = [];

        foreach ($this->fileLanguages() as $language) {
            foreach ($counted as $key => $parts) {
                for ($part = 1; $part <= $parts; $part++) {
                    $files[] = self::name($language, $key, $part);
                }
            }
        }

        return $files;
    }

    /**
     * The entries of one file, or `null` for a name the feed does not have.
     *
     * @return list<array{url: string, lastModified?: string, alternates?: array{languages: array<string, string>}}>|null
     */
    public function file(string $name): ?array
    {
        if (preg_match('/^(.+)-([1-9][0-9]{0,8})$/', $name, $matches) !== 1) {
            return null;
        }

        $part = (int) $matches[2];
        $perFile = $this->perFile();
        $sources = $this->registry->sources();

        foreach ($this->fileLanguages() as $language) {
            foreach ($sources as $source) {
                if (self::name($language, $source->key(), $part) !== $name) {
                    continue;
                }

                $offset = ($part - 1) * $perFile;

                if ($offset >= $source->count()) {
                    return null;
                }

                return $this->entries($source->items($offset, $perFile), $language);
            }
        }

        return null;
    }

    /**
     * @param  list<SitemapItem>  $items
     * @return list<array{url: string, lastModified?: string, alternates?: array{languages: array<string, string>}}>
     */
    private function entries(array $items, ?string $language): array
    {
        $default = $this->languages->defaultLocale();
        $entries = [];

        foreach ($items as $item) {
            $entry = ['url' => $this->siteUrl->absolute($item->path, $language ?? $default)];

            // Left out rather than written as null: the field is optional in
            // the protocol and in the type of Next alike.
            if ($item->lastModified !== null) {
                $entry['lastModified'] = $item->lastModified->format(DATE_ATOM);
            }

            if ($language !== null) {
                $entry['alternates'] = ['languages' => $this->versions($item->path, $default)];
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * Every language version of a path, the one at hand included, plus
     * `x-default` on the default language: the set is the same for every
     * version, which is what makes the annotation reciprocal.
     *
     * @return array<string, string>
     */
    private function versions(string $path, string $default): array
    {
        $versions = [];

        foreach ($this->languages->locales() as $locale) {
            $versions[$locale] = $this->siteUrl->absolute($path, $locale);
        }

        $versions['x-default'] = $this->siteUrl->absolute($path, $default);

        return $versions;
    }

    /**
     * The languages a file is cut by, or a single `null` when addresses do not
     * differ by language.
     *
     * @return list<string|null>
     */
    private function fileLanguages(): array
    {
        $locales = $this->languages->locales();

        if (count($locales) < 2 || ! $this->siteUrl->prefixesLanguage()) {
            return [null];
        }

        return $locales;
    }

    private static function name(?string $language, string $source, int $part): string
    {
        return ($language === null ? '' : $language.'-').$source.'-'.$part;
    }

    private function perFile(): int
    {
        $configured = $this->config->get('cms-seo.sitemap.per_file', self::DEFAULT_PER_FILE);
        $perFile = is_numeric($configured) ? (int) $configured : self::DEFAULT_PER_FILE;

        return max(1, min(self::MAX_PER_FILE, $perFile));
    }
}
