<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Actions;

/**
 * A named state of the frontend that has an address. A purchase is the one the
 * first version registers, and it is registered by the application: the core
 * knows no entities, so it cannot know what «buy» leads to.
 *
 * The address is a full one on purpose. An action is navigation into a state,
 * not the absence of navigation, so the link works in a letter, in a preview
 * and in a sitemap exactly like any other; the `action` object of the payload
 * is the hint about what opens there, not a fallback path.
 */
interface LinkAction
{
    /**
     * The code stored in the link, e.g. `buy`.
     */
    public function name(): string;

    /**
     * The address this action opens at, or null when its parameters point at
     * nothing that exists.
     *
     * @param  array<string, mixed>  $params
     */
    public function href(array $params, string $locale): ?string;
}
