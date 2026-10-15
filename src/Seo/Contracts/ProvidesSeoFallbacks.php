<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Contracts;

/**
 * What an owner answers when its metadata is not filled in. The fallback is
 * computed when the document is handed out and never written into the row:
 * materialising it once at creation is how a renamed tour keeps a stale title.
 *
 * Only the title and the picture have a fallback. The description has none on
 * purpose: a description taken from the first paragraph is a classic source
 * of rubbish in search results.
 *
 * Implemented by the model of the installation — the name of a tour and the
 * cover of an event are exactly what a reusable package cannot know.
 */
interface ProvidesSeoFallbacks
{
    /**
     * The human name of the entity on this language, or `null` when the
     * entity has none on it.
     */
    public function seoFallbackTitle(string $locale): ?string;

    /**
     * An absolute address of the cover picture, or `null`. The last line of
     * defence stays with the frontend: a shared spare picture is a matter of
     * presentation, not of content.
     */
    public function seoFallbackImage(string $locale): ?string;
}
