<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

/**
 * One kind of address the sitemap lists: the pages of the tree, or the entities
 * of one template page type.
 *
 * A source knows nothing about languages: it answers paths, and the feed
 * turns every path into an address per language. That is what lets one
 * source serve a single-language site and a multilingual one alike.
 *
 * `count()` and `items()` read the very same set in the very same order: the
 * feed cuts the set into files by offset, and a file the index announced has
 * to exist when the frontend asks for it.
 */
interface SitemapSource
{
    /**
     * What the files of this source are named by, kebab-case: `pages`,
     * `event`. Unique among the sources of the feed.
     */
    public function key(): string;

    public function count(): int;

    /**
     * A slice of the set, ordered by a column that does not move. A record
     * whose address cannot be built is left out of its slice rather than
     * moving the borders of the next one.
     *
     * @return list<SitemapItem>
     */
    public function items(int $offset, int $limit): array;
}
