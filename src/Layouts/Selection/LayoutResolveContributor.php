<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Selection;

use Ewk\Cms\Pages\Http\Resolve\ResolveContributor;
use Ewk\Cms\Pages\Http\Resolve\ResolveDocument;
use Illuminate\Http\Request;

/**
 * How the chosen shell reaches `GET /v1/resolve`: the code of the layout
 * travels in the answer of the resolver, next to the type of the page and its
 * blocks.
 *
 * A contributor and not a call inside the resolver, for the same reason the
 * metadata of the SEO part arrive that way: the pages may not depend on the
 * layouts. So the resolver writes `page.layout_code` straight off the row, and
 * this contributor replaces it with the answer of {@see LayoutSelector} — the
 * layout the page names when it exists and is active, otherwise the default
 * one.
 *
 * Without this contributor registered the raw column is all anyone knows, and
 * that is exactly what the resolver keeps writing.
 *
 * {@see LayoutSelection::NO_DEFAULT} delivers `null` and never a 500: an
 * installation without a default layout is a configuration mistake of the
 * editor's side, and a public address must not answer with an error because of
 * it. Null is the honest value — the frontend picks a shell for a code it does
 * not know anyway — while the code the page asked for would be a lie: no such
 * layout can be drawn.
 */
final readonly class LayoutResolveContributor implements ResolveContributor
{
    public function __construct(private LayoutSelector $selector)
    {
    }

    public function contribute(ResolveDocument $document, Request $request): void
    {
        $page = $document->get('page');

        if (! is_array($page)) {
            return;
        }

        // The column of the page and not the payload: a template address is
        // drawn by the shell of its template page, and that is what the
        // resolved address carries.
        $page['layout_code'] = $this->selector->select($document->resolved()->layoutCode())->code();

        $document->set('page', $page);
    }
}
