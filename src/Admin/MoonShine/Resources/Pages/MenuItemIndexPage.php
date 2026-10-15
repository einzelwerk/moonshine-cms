<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\MenuItemResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Illuminate\Http\Exceptions\HttpResponseException;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;

use function redirect;

/**
 * Items are reached from the tree on the form of their menu and from nowhere
 * else. The flat list of every item of every menu is not a section an editor
 * works in: it says nothing about which menu a row belongs to and offers a
 * reorder that has no meaning across menus. The address stays, because the
 * resource redirects to it after a delete, and it leads where the items are
 * actually edited.
 *
 * The fields are still declared: a list component of this resource is what
 * the panel builds a preview switcher from.
 *
 * @extends IndexPage<MenuItemResource>
 */
class MenuItemIndexPage extends IndexPage
{
    public function prepareBeforeRender(): void
    {
        parent::prepareBeforeRender();

        throw new HttpResponseException(
            redirect($this->getCore()->getContainer(MenuResource::class)->getIndexPageUrl()),
        );
    }

    /**
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            ID::make()->sortable(),
            TranslatableText::make(__('moonshine-cms::menus.item_label'), 'label'),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->updateOnPreview(),
        ];
    }
}
