<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use DateTimeInterface;

/**
 * One address of the sitemap before it meets a language: the normalized
 * path of a page, and the moment its content last changed.
 */
final readonly class SitemapItem
{
    /**
     * @param  string  $path  The normalized path, `/` for the root, without a language.
     * @param  DateTimeInterface|null  $lastModified  `null` when nothing honest can be said: an inaccurate date makes the search engine stop trusting the field at all.
     */
    public function __construct(
        public string $path,
        public ?DateTimeInterface $lastModified = null,
    ) {
    }

    /**
     * The latest of the moments that change what a visitor reads. `null`
     * values are skipped, and nothing at all gives `null`.
     */
    public static function latest(?DateTimeInterface ...$moments): ?DateTimeInterface
    {
        $latest = null;

        foreach ($moments as $moment) {
            if ($moment !== null && ($latest === null || $moment > $latest)) {
                $latest = $moment;
            }
        }

        return $latest;
    }
}
