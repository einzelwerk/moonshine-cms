<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Documents;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything the package needs about one request to build the metadata of one
 * page. The caller assembles it — the resolver of the pages through a
 * contributor, see {@see \Ewk\Cms\Seo\Resolve\SeoResolveContributor}.
 *
 * Nothing here is read out of the container or out of the request: the
 * document builder is a pure function of this object plus the stored row, and
 * that is what makes the canonical address testable without an HTTP layer.
 */
final readonly class SeoContext
{
    /**
     * @param  Model  $owner  The page or the entity the metadata belongs to.
     * @param  string  $locale  The language of the request.
     * @param  string  $path  The normalized path of the page, `/` for the root.
     * @param  array<array-key, mixed>  $query  Normalized block parameters, nested: `['b' => ['afisha' => ['page' => 2]]]`.
     * @param  list<string>  $filterKeys  Flat names of the filtering parameters, `b[afisha][city]`, derived from the schemas of the blocks of the page.
     * @param  list<string>  $languages  Content languages of the catalog, bare codes.
     * @param  string  $defaultLanguage  The language `x-default` points at.
     * @param  DateTimeInterface|null  $publishedAt  `null` for a page that is not published: it has no language versions, it does not exist for a visitor.
     * @param  string|null  $fallbackTitle  What goes into an empty title, put here by the caller. Wins over {@see \Ewk\Cms\Seo\Contracts\ProvidesSeoFallbacks} of the owner.
     * @param  string|null  $fallbackImage  What goes into an empty card picture, an absolute address. Wins over the contract of the owner too.
     */
    public function __construct(
        public Model $owner,
        public string $locale,
        public string $path,
        public array $query,
        public array $filterKeys,
        public array $languages,
        public string $defaultLanguage,
        public ?DateTimeInterface $publishedAt = null,
        // The name of a page of `cms_pages` arrives this way and not through
        // the contract: the pages do not depend on the SEO part, so their model
        // implements none of its contracts, so the fallback of a page is put in
        // by the contributor that assembles this context.
        public ?string $fallbackTitle = null,
        public ?string $fallbackImage = null,
    ) {
    }
}
