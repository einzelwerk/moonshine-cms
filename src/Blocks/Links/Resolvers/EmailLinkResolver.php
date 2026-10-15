<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Resolvers;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;

final readonly class EmailLinkResolver implements LinkResolverContract
{
    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::EMAIL;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $email = $link->attribute('email');

        return new ResolvedLink(
            kind: LinkKind::EMAIL,
            href: is_string($email) && $email !== '' ? 'mailto:'.$email : null,
            external: false,
            newTab: $link->newTab,
        );
    }
}
