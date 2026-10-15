<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

/**
 * One resolver for every consumer of links: blocks, menus, metadata, mail.
 *
 * The registry tells two situations apart:
 *
 * - **no resolver for the kind** is a configuration mistake. It is visible
 *   through {@see self::has()}, which {@see \Ewk\Cms\Blocks\Fields\LinkField}
 *   asks before letting an editor store such a link at all, so the mistake
 *   surfaces in the form rather than on the site.
 * - **the target cannot be reached** is content: the link keeps its kind and
 *   carries `href: null`.
 *
 * {@see self::resolve()} answers with the broken link in both cases and never
 * throws: it is on the path of a public answer, and a page must not go down
 * because one button of one block points at nothing.
 */
final class LinkResolverRegistry
{
    /** @var list<LinkResolverContract> */
    private array $resolvers = [];

    public function register(LinkResolverContract $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    public function has(LinkKind $kind): bool
    {
        return $this->for($kind) instanceof LinkResolverContract;
    }

    /**
     * The resolver answering this kind, the first registered one winning, or
     * null while nothing answers it.
     */
    public function for(LinkKind $kind): ?LinkResolverContract
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($kind)) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Hands every resolver able to read in bulk the links of its own kinds,
     * see {@see PreloadsLinks}. A caller holding a whole list of links — a
     * menu — calls this before resolving them one by one; skipping it changes
     * the number of queries and never an answer.
     *
     * @param  iterable<Link>  $links
     */
    public function preload(iterable $links): void
    {
        /** @var array<int, PreloadsLinks> $resolvers */
        $resolvers = [];
        /** @var array<int, list<Link>> $batches */
        $batches = [];

        foreach ($links as $link) {
            $resolver = $this->for($link->kind);

            if (! $resolver instanceof PreloadsLinks) {
                continue;
            }

            $id = spl_object_id($resolver);
            $resolvers[$id] = $resolver;
            $batches[$id][] = $link;
        }

        foreach ($batches as $id => $batch) {
            $resolvers[$id]->preload($batch);
        }
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        return $this->for($link->kind)?->resolve($link, $locale) ?? new ResolvedLink(
            kind: $link->kind,
            href: null,
            external: false,
            newTab: $link->newTab,
        );
    }
}
