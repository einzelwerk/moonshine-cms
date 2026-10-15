<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Resolvers;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;

final readonly class PhoneLinkResolver implements LinkResolverContract
{
    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::PHONE;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $phone = $link->attribute('phone');

        return new ResolvedLink(
            kind: LinkKind::PHONE,
            href: is_string($phone) && $phone !== '' ? 'tel:'.$phone : null,
            external: false,
            newTab: $link->newTab,
        );
    }
}
