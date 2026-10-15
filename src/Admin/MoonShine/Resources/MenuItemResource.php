<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\MoonShineCms\Actions\SaveMenuItem;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuItemFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuItemIndexPage;
use MoonShine\Crud\Attributes\SaveHandler;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;
use MoonShine\Support\Enums\SortDirection;

/**
 * Entries of a menu, reached from the tree on the menu form and from nowhere
 * else, hence no menu entry of their own.
 *
 * Saving and deleting return to that tree: the item form is a detour from the
 * menu, not a section an editor works in.
 *
 * @extends ModelResource<CmsMenuItem, MenuItemIndexPage, MenuItemFormPage>
 */
#[SkipMenu]
#[SaveHandler(SaveMenuItem::class)]
class MenuItemResource extends ModelResource
{
    protected bool $usePagination = false;

    protected string $model = CmsMenuItem::class;

    protected string $column = 'id';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::menus.items');
    }

    protected function pages(): array
    {
        return [
            MenuItemIndexPage::class,
            MenuItemFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id'];
    }

    public function getRedirectAfterSave(): ?string
    {
        return $this->menuFormUrl() ?? parent::getRedirectAfterSave();
    }

    public function getRedirectAfterDelete(): string
    {
        return $this->menuFormUrl() ?? parent::getRedirectAfterDelete();
    }

    /**
     * The form of the menu the current item belongs to, or null when there is
     * no item to ask.
     */
    private function menuFormUrl(): ?string
    {
        $item = $this->getItem();

        if (! $item instanceof CmsMenuItem || $item->cms_menu_id <= 0) {
            return null;
        }

        return $this->getCore()->getContainer(MenuResource::class)->getFormPageUrl($item->cms_menu_id);
    }
}
