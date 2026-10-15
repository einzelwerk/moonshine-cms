<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Selection;

use Ewk\Cms\Layouts\Models\CmsLayout;

/**
 * Which shell a page is drawn in. Two steps and no more: the layout the page
 * names, otherwise the one marked as the default. There is deliberately no
 * "default per page type" level and no inheritance along the tree.
 *
 * The page arrives here as a plain string. This package knows nothing about
 * pages — that is the whole reason the code and not an identifier is stored on
 * the page side, and the reason the pages do not depend on the layouts.
 *
 * A code that names no active layout is not an error either: the layout may
 * have been switched off after the page was saved, so the default answers and
 * {@see SelectedLayout::fellBack()} tells the caller it happened.
 */
final readonly class LayoutSelector
{
    /**
     * @param  string|null  $layoutCode  the `layout_code` of the page, if it has one
     */
    public function select(?string $layoutCode): SelectedLayout
    {
        $code = $layoutCode === null ? null : trim($layoutCode);
        $code = $code === '' ? null : $code;

        if ($code !== null) {
            $requested = CmsLayout::query()
                ->where('code', $code)
                ->where('is_active', true)
                ->first();

            if ($requested instanceof CmsLayout) {
                return SelectedLayout::requested($requested);
            }
        }

        $default = CmsLayout::query()
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $default instanceof CmsLayout
            ? SelectedLayout::fallback($default, $code)
            : SelectedLayout::noDefault($code);
    }
}
