<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Events;

use Ewk\Cms\Pages\Http\Resolve\LocatedPage;
use Ewk\Cms\Pages\Models\CmsPage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * A visitor was answered with a whole page: the address resolved, the snapshot
 * was rendered and the contributors wrote their part of the document.
 *
 * This is the point an installation hangs what a page view means to it on,
 * the history of what a visitor looked at for one, without the package knowing
 * anything about it. It is fired by the resolver of a whole page only and never
 * by the endpoint of a single block: the block of a page already shown is a
 * continuation of that view, not a view of its own.
 *
 * A miss, a draft and a language prefix fire nothing.
 */
final readonly class PageResolved
{
    public function __construct(
        public LocatedPage $located,
        public Request $request,
    ) {
    }

    /**
     * The page that owns the blocks: the page itself, or the template of the
     * type of the subject.
     */
    public function page(): CmsPage
    {
        return $this->located->page;
    }

    /**
     * The entity behind a template address, `null` on a page of its own.
     */
    public function subject(): ?Model
    {
        return $this->located->resolved->subject;
    }
}
