<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use Closure;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * The sources of the sitemap.
 *
 * Nothing has to be registered for a site to have one: the pages of the tree
 * come first, then the entities of every template type, read out of the
 * registry of types, which is already the content half of the route manifest. A
 * second list of what is public would be one more thing to keep in sync.
 *
 * What an installation adds:
 *
 * - **narrowing** of a template type, for what opens but should not be
 *   listed — an event long gone, a record hidden from the lists;
 * - **a source of its own**, for addresses that are neither a page nor an
 *   entity of a template type.
 *
 * Both are attached as container extenders, the way the JSON-LD builders are.
 */
final class SitemapRegistry
{
    /** @var array<string, list<Closure(Builder<Model>): void>> */
    private array $narrowing = [];

    /** @var list<SitemapSource> */
    private array $extra = [];

    public function __construct(private readonly PageTypeRegistry $types)
    {
    }

    /**
     * Narrow the set of one template type. The callback receives the query
     * after the visibility criterion of the type, and several callbacks of
     * one type all apply.
     *
     * @param  Closure(Builder<Model>): void  $callback
     */
    public function narrow(string $type, Closure $callback): void
    {
        $this->narrowing[$type][] = $callback;
    }

    public function register(SitemapSource $source): void
    {
        $this->extra[] = $source;
    }

    /**
     * Every source in the order the files are listed: pages, the template
     * types in the order they were declared, then the sources of the
     * installation.
     *
     * @return list<SitemapSource>
     *
     * @throws InvalidArgumentException when two sources share a key: their files would share names
     */
    public function sources(): array
    {
        $sources = [new PageSitemapSource($this->types)];

        foreach ($this->types->all() as $key => $type) {
            if ($type->isTemplate()) {
                $sources[] = new SubjectSitemapSource($type, $this->narrowing[$key] ?? []);
            }
        }

        $seen = [];

        foreach ([...$sources, ...$this->extra] as $source) {
            $key = $source->key();

            if (isset($seen[$key])) {
                throw new InvalidArgumentException(sprintf('Two sitemap sources share the key [%s].', $key));
            }

            $seen[$key] = true;
        }

        return [...$sources, ...$this->extra];
    }
}
