<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Resolvers;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Links\NamesLinkOwner;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Blocks\Links\ValidatesLinkTarget;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

/**
 * A block of a page addressed by its anchor. The anchor is the same identifier
 * the parameters of a dynamic block live under in the query string: a block has
 * one human readable name on the outside, not two.
 *
 * Without a page the link stays inside the page being read and resolves to the
 * bare fragment, which is what the button «to the schedule» of a hero needs.
 * With a page the address of that page is put in front of it, and the address
 * of a page is something only the resolver of the `page` kind knows: this one
 * asks the registry instead of repeating the lookup, so a page switched off or
 * deleted breaks both kinds of link in exactly the same way.
 */
final class AnchorLinkResolver implements LinkResolverContract, ValidatesLinkTarget
{
    /**
     * Anchors are generated out of block codes, so they are lower case
     * kebab identifiers; anything else in the column is not an anchor of this
     * CMS, and `#Raspisanie` is not the same fragment as `#raspisanie`.
     */
    private const string ANCHOR_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';

    public function __construct(private readonly Container $container)
    {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::ANCHOR;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $anchor = self::anchor($link->attribute('anchor'));
        $page = $link->attribute('page');

        if ($anchor === null) {
            return $this->broken($link);
        }

        if (! is_string($page) || trim($page) === '') {
            return new ResolvedLink(
                kind: LinkKind::ANCHOR,
                href: '#'.$anchor,
                external: false,
                newTab: $link->newTab,
            );
        }

        $path = $this->pathOf(trim($page), $locale);

        return new ResolvedLink(
            kind: LinkKind::ANCHOR,
            href: $path === null ? null : $path.'#'.$anchor,
            external: false,
            newTab: $link->newTab,
        );
    }

    /**
     * What only this resolver can answer: the shape of the anchor always, and,
     * when the editor named a page, whether that page carries a block under
     * this anchor at all. The draft counts — a block added and not published
     * yet is still the block being pointed at.
     *
     * A page that cannot be found is not a refusal: a target deleted after the
     * link was written must leave the link broken and not make the block that
     * holds it unsavable.
     */
    public function problem(array $attributes): ?string
    {
        $anchor = self::anchor($attributes['anchor'] ?? null);

        if ($anchor === null) {
            return (string) __('cms-blocks::validation.link.anchor_format');
        }

        $page = $attributes['page'] ?? null;

        if (! is_string($page) || trim($page) === '') {
            return null;
        }

        $owner = $this->ownerOf(trim($page));

        if ($owner === null || self::carriesAnchor($owner, $anchor)) {
            return null;
        }

        return (string) __('cms-blocks::validation.link.unknown_anchor', ['anchor' => $anchor]);
    }

    /**
     * The address of the page the anchor sits on, through the resolver that
     * owns pages. Nothing registered for the `page` kind means no address, the
     * same answer a deleted page gives.
     *
     * Nothing is remembered here: this resolver lives in a registry built once
     * per worker, and an address kept in it would outlive the request — a page
     * renamed would keep its old address until the worker restarts. Reading a
     * page once per request is the business of the resolver of pages, which
     * keeps what it read in a scope of the request.
     */
    private function pathOf(string $page, string $locale): ?string
    {
        if (! $this->container->bound(LinkResolverRegistry::class)) {
            return null;
        }

        return $this->container->make(LinkResolverRegistry::class)->resolve(
            new Link(LinkKind::PAGE, ['page' => $page]),
            $locale,
        )->href;
    }

    /**
     * The record the page target stands for, named by whoever resolves pages.
     * This package owns blocks and not pages, see {@see NamesLinkOwner}.
     */
    private function ownerOf(string $page): ?Model
    {
        if (! $this->container->bound(LinkResolverRegistry::class)) {
            return null;
        }

        $resolver = $this->container->make(LinkResolverRegistry::class)->for(LinkKind::PAGE);

        return $resolver instanceof NamesLinkOwner ? $resolver->ownerOf(['page' => $page]) : null;
    }

    private static function carriesAnchor(Model $owner, string $anchor): bool
    {
        return CmsBlock::query()
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey())
            ->where('anchor', $anchor)
            ->exists();
    }

    private function broken(Link $link): ResolvedLink
    {
        return new ResolvedLink(
            kind: LinkKind::ANCHOR,
            href: null,
            external: false,
            newTab: $link->newTab,
        );
    }

    /**
     * The `#` an editor types in front of the anchor is removed: it belongs to
     * the address, not to the identifier of the block.
     */
    private static function anchor(mixed $raw): ?string
    {
        if (! is_string($raw)) {
            return null;
        }

        $anchor = ltrim(trim($raw), '#');

        return preg_match(self::ANCHOR_PATTERN, $anchor) === 1 ? $anchor : null;
    }
}
