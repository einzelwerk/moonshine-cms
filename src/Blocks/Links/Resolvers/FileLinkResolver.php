<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links\Resolvers;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Blocks\Links\LinkResolverContract;
use Ewk\Cms\Blocks\Links\ResolvedLink;
use Ewk\Cms\Media\Models\CmsMedia;
use Illuminate\Contracts\Config\Repository;

/**
 * A file of the media library: the link stores the uuid of the record and the
 * address is read off the disk that record lives on.
 *
 * A record that is gone leaves `href: null`. That is a rare state on purpose —
 * a file in use cannot be deleted — but a row written before the usages were
 * registered may still point at nothing.
 *
 * Private files with signed addresses are out of the first version, so the
 * address here is the plain public one.
 */
final readonly class FileLinkResolver implements LinkResolverContract
{
    public function __construct(private Repository $config)
    {
    }

    public function supports(LinkKind $kind): bool
    {
        return $kind === LinkKind::FILE;
    }

    public function resolve(Link $link, string $locale): ResolvedLink
    {
        $href = $this->href($link->attribute('file'));

        return new ResolvedLink(
            kind: LinkKind::FILE,
            href: $href,
            // A disk of its own is a host of its own: a file served from a
            // bucket or a CDN is external to the frontend and needs the
            // protective attributes of one.
            external: $href !== null && $this->isForeignHost($href),
            newTab: $link->newTab,
        );
    }

    private function href(mixed $uuid): ?string
    {
        if (! is_string($uuid) || trim($uuid) === '') {
            return null;
        }

        $media = CmsMedia::query()->where('uuid', trim($uuid))->first();

        return $media instanceof CmsMedia ? $media->url() : null;
    }

    private function isForeignHost(string $href): bool
    {
        $host = parse_url($href, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $site = $this->config->get('app.url');
        $siteHost = is_string($site) ? parse_url($site, PHP_URL_HOST) : null;

        return is_string($siteHost) && $siteHost !== '' && strcasecmp($host, $siteHost) !== 0;
    }
}
