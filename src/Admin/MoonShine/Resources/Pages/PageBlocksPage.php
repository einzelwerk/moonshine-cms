<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\MoonShineCms\MoonShine\Pages\BlockEditorPage;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\Support\ModelKey;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Database\Eloquent\Model;

/**
 * The block editor of one page, on a page of its own next to the form: a
 * card of the editor is a form, and a form inside the form of the resource
 * is invalid HTML.
 *
 * @extends BlockEditorPage<PageResource>
 */
class PageBlocksPage extends BlockEditorPage
{
    protected function ownerCaption(Model $owner): string
    {
        if (! $owner instanceof CmsPage) {
            return (string) ModelKey::of($owner);
        }

        $caption = Translated::caption($owner->getAttribute('name'));

        return $caption !== '' ? $caption : $owner->path;
    }
}
