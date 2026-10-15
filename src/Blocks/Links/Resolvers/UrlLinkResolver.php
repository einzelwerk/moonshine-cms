<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Resolvers;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;

/**
 * An external address resolves to itself. The scheme allow-list is enforced
 * while validating the field, so nothing unsafe reaches storage.
 */
final readonly class UrlLinkResolver implements LinkResolverContract
{
    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::URL;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $url = $link->attribute('url');

        return new ResolvedLink(
            kind: LinkKind::URL,
            href: is_string($url) && $url !== '' ? $url : null,
            external: true,
            newTab: $link->newTab,
        );
    }
}
