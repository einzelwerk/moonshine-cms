<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

interface LinkResolverContract
{
    public function supports(LinkKind $kind): bool;

    public function resolve(Link $link, string $locale): ResolvedLink;
}
