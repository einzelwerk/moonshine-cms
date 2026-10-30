<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Contracts;

/**
 * An owner with a text of its own to stand in for an empty description: the
 * description an organizer wrote for a tour, the about text of an artist. Not
 * the first paragraph of a page — that is the rubbish {@see ProvidesSeoFallbacks}
 * keeps out, which is why this is a separate contract an installation opts
 * into per model.
 *
 * The answer is plain text of any length. The document shortens it to the
 * size of a description and puts it into Open Graph and Twitter with the rest.
 */
interface ProvidesSeoDescriptionFallback
{
    /**
     * The text on this language, or `null` when the entity has none on it.
     */
    public function seoFallbackDescription(string $locale): ?string;
}
