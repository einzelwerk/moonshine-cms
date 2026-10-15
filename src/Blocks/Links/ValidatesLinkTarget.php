<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

/**
 * A resolver that can also answer whether a target is well formed. Two kinds
 * need it: `route`, whose names come from a manifest, and `action`, whose codes
 * come from a registry. Both describe a closed set an editor picks from, so a
 * value outside that set is a mistake in the form and not a target that went
 * away.
 *
 * Existence of a record — an entity, a page, a file — is deliberately not
 * asked here: a record deleted after the link was written must leave the link
 * broken (`href: null`) and not make its whole block unsavable.
 */
interface ValidatesLinkTarget
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return string|null  the reason the target cannot work, already translated, or null when it can
     */
    public function problem(array $attributes): ?string;
}
