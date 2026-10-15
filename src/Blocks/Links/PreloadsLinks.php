<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

/**
 * A resolver that can read the targets of many links at once.
 *
 * A menu is a list of links, and resolving them one by one is one lookup per
 * item: forty items of a chrome are forty reads of pages most of which are
 * the same handful. Whoever holds the whole list hands it over first, see
 * {@see LinkResolverRegistry::preload()}, and the resolver reads what the
 * list names in one go; {@see LinkResolverContract::resolve()} then finds it
 * read.
 *
 * Preloading is an optimisation and never a condition: a link that was not
 * preloaded resolves exactly as before, and the answer of a preloaded one is
 * the very same. What was read lives no longer than the request it was read
 * for — a resolver is registered once per worker, so it keeps nothing itself.
 */
interface PreloadsLinks
{
    /**
     * @param  list<Link>  $links  links of the kinds this resolver supports
     */
    public function preload(array $links): void;
}
