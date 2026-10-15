<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Resolving\ResolvedPath;

/**
 * An address that resolved to something published: the page that owns the
 * blocks — its own, or the template of the subject's type — together with the
 * snapshot a visitor reads.
 *
 * Both entrances of the API stop here: the resolver of a whole page goes on to
 * render every block, the endpoint of one block goes on to find that block in
 * this very snapshot.
 */
final readonly class LocatedPage
{
    public function __construct(
        public ResolvedPath $resolved,
        public CmsPage $page,
        public CmsVersion $version,
    ) {
    }
}
