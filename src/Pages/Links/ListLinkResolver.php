<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Links;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\PreloadsLinks;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Params\PageQuery;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Types\PageTypeRegistry;

/**
 * «Все события в Праге» and «концерты этой категории» lead to the catalog with
 * filters chosen, not to pages of their own.
 *
 * Stored are the target page and the values of the filters. The anchor those
 * values live under, is found here: on the target page the listing block
 * declaring exactly these parameters is looked for and its anchor is taken. One
 * candidate and the editor never thinks about anchors at all; none or several
 * without an explicit anchor and the link is delivered broken — `href: null` —
 * because guessing one of several lists would silently filter the wrong one.
 *
 * The same resolver builds the target of the `redirect` mode of a filter panel:
 * the panel of the home page leads into the catalog by the rules of the
 * catalog's own block.
 *
 * The page and its blocks are read through the request scoped
 * {@see PageLookup}: seven links «concerts», «theatre», … into one catalog are
 * one read of the page and one of its blocks, not seven of each.
 */
final readonly class ListLinkResolver implements LinkResolverContract, PreloadsLinks
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private PageTypeRegistry $types,
    ) {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::LISTING;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $page = $this->page($link->attribute('page'));
        $filters = $this->filters($link->attribute('filters'));

        return new ResolvedLink(
            kind: LinkKind::LISTING,
            href: $page === null ? null : $this->hrefOf($page, $link->attribute('anchor'), $filters),
            external: false,
            newTab: $link->newTab,
        );
    }

    public function preload(array $links): void
    {
        PageLookup::current()->preloadTargetsOf($links);
    }

    /**
     * Without a single filter there is nothing to namespace, so the link is
     * the plain address of the page and no anchor is looked for: a target
     * page with two lists on it is not ambiguous until a value has to land in
     * one of them.
     *
     * @param  array<string, mixed>  $filters
     */
    private function hrefOf(CmsPage $page, mixed $declared, array $filters): ?string
    {
        if ($filters === []) {
            return $page->path;
        }

        $anchor = $this->anchorOf($page, $declared, $filters);

        return $anchor === null ? null : $this->href($page, $anchor, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters  never empty here, see {@see ListLinkResolver::hrefOf()}
     */
    private function href(CmsPage $page, string $anchor, array $filters): string
    {
        // The one encoder of the CMS, so this address and the pagination
        // links of the very same block are written byte for byte alike.
        return $page->path.'?'.PageQuery::encode(['b' => [$anchor => $filters]]);
    }

    /**
     * The anchor the editor named, once it is confirmed to exist, or the one
     * of the single block that declares every filter passed.
     *
     * @param  array<string, mixed>  $filters
     */
    private function anchorOf(CmsPage $page, mixed $declared, array $filters): ?string
    {
        $blocks = $this->blocksOf($page);

        if (is_string($declared) && trim($declared) !== '') {
            $anchor = trim($declared);

            foreach ($blocks as $block) {
                if ($block->anchor === $anchor) {
                    return $anchor;
                }
            }

            return null;
        }

        $candidates = [];

        foreach ($blocks as $block) {
            $anchor = $block->anchor;

            if ($anchor === null || $anchor === '' || ! $this->declaresAll($block, $filters)) {
                continue;
            }

            $candidates[$anchor] = true;
        }

        $anchors = array_keys($candidates);

        return count($anchors) === 1 ? (string) $anchors[0] : null;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function declaresAll(CmsBlock $block, array $filters): bool
    {
        if (! $this->registry->has($block->type)) {
            return false;
        }

        $schema = $this->registry->schemaFor($block->type);

        if (! $schema->isDynamic()) {
            return false;
        }

        $declared = [];

        foreach ($schema->params() as $param) {
            $declared[$param->name()] = true;
        }

        foreach (array_keys($filters) as $name) {
            if (! isset($declared[$name])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The live rows of the page: a link is resolved for the editor as well as
     * for a visitor, and a block added but not published yet is still the
     * block the editor is pointing at.
     *
     * @return list<CmsBlock>
     */
    private function blocksOf(CmsPage $page): array
    {
        return PageLookup::current()->activeBlocksOf($page);
    }

    private function page(mixed $uuid): ?CmsPage
    {
        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        $page = PageLookup::current()->page($uuid);

        if (! $page instanceof CmsPage || ! $page->is_active) {
            return null;
        }

        // A template page holds the blocks of a whole type and is never opened
        // by its own stored address: a catalog of filters cannot live at an
        // address that does not exist.
        return $this->types->has($page->type) && $this->types->get($page->type)->isTemplate()
            ? null
            : $page;
    }

    /**
     * Only scalar values reach an address; an empty one means "no filter" and
     * is dropped, exactly as the parameter grammar drops it.
     *
     * @return array<string, mixed>
     */
    private function filters(mixed $filters): array
    {
        if (! is_array($filters)) {
            return [];
        }

        $kept = [];

        foreach ($filters as $name => $value) {
            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $kept[(string) $name] = $value;
        }

        return $kept;
    }
}
