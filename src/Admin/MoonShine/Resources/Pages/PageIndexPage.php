<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\MoonShineCms\MoonShine\Components\PageTreeView;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The «Pages» section is a tree and not a table: the list of the crud page is
 * replaced by {@see PageTreeView}, the top buttons of the crud page (create,
 * search) stay.
 *
 * @extends IndexPage<PageResource>
 */
class PageIndexPage extends IndexPage
{
    /**
     * The columns of the crud page. The tree does not draw them, but the
     * resource still describes its rows through them — export, the column
     * picker of MoonShine — so they are declared.
     *
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make(__('moonshine-cms::fields.name'), 'name'),
            Text::make(__('moonshine-cms::pages.path'), 'path'),
            Text::make(__('moonshine-cms::pages.type'), 'type'),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active'),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    protected function mainLayer(): array
    {
        $resource = $this->getResource();

        if (! $resource instanceof PageResource) {
            return parent::mainLayer();
        }

        return [
            Fragment::make([PageTreeView::forResource($resource, $this)])->name(PageTreeView::FRAGMENT),
        ];
    }
}
