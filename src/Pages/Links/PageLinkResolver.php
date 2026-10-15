<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Links;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\NamesLinkOwner;
use Ewk\Cms\Blocks\Links\PreloadsLinks;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Eloquent\Model;

/**
 * The address of a page of this CMS: the target is stored as the uuid of the
 * page and never as a ready address, because an address goes stale on the first
 * rename while the uuid does not.
 *
 * A page that is switched off is delivered exactly like a page that no longer
 * exists — `href: null`. The distinction is meaningless to a visitor, and the
 * consumers of a link already treat the empty address as the broken one, see
 * {@see \Ewk\Cms\Blocks\Links\LinkResolverRegistry}.
 *
 * A template page is delivered the same way: it holds the blocks of a whole
 * type and is never opened by its own stored address, so pointing a link at it
 * would point at nothing.
 *
 * The resolver is registered by the provider of this package, together with
 * {@see ListLinkResolver}: both answer addresses this package owns. Pages are
 * read through the request scoped {@see PageLookup}, so a menu of forty links
 * to a handful of pages is one query and not forty.
 */
final readonly class PageLinkResolver implements LinkResolverContract, NamesLinkOwner, PreloadsLinks
{
    public function __construct(private PageTypeRegistry $types)
    {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::PAGE;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $page = $this->page($link->attribute('page'));

        return new ResolvedLink(
            kind: LinkKind::PAGE,
            href: $page?->path,
            // A page of this installation is an address of this installation.
            external: false,
            newTab: $link->newTab,
        );
    }

    public function preload(array $links): void
    {
        PageLookup::current()->preloadTargetsOf($links);
    }

    /**
     * Which record the target names, for a resolver of another kind that has
     * to look at the blocks of that page, see {@see NamesLinkOwner}: the
     * anchor of a link is a block of this row. A page switched off or holding
     * no address of its own is still that row, so nothing is filtered here.
     */
    public function ownerOf(array $attributes): ?Model
    {
        $uuid = $attributes['page'] ?? null;

        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        return PageModel::query()->where('uuid', trim($uuid))->first();
    }

    /**
     * An addressable, active page, or null. A page of a template type has no
     * address of its own, so it is as unreachable as a deleted one.
     */
    private function page(mixed $uuid): ?CmsPage
    {
        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        $page = PageLookup::current()->page($uuid);

        if (! $page instanceof CmsPage || ! $page->is_active) {
            return null;
        }

        return $this->types->has($page->type) && $this->types->get($page->type)->isTemplate()
            ? null
            : $page;
    }
}
