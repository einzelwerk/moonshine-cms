<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Media\MoonShine\Library\MediaAssets;
use Ewk\MoonShineCms\Editor\EditorAssets;
use Ewk\MoonShineCms\MoonShine\Resources\MenuItemResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuItemFormPage;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Support\Collection;
use MoonShine\AssetManager\InlineCss;
use MoonShine\AssetManager\InlineJs;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Contracts\UI\ModalContract;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * The items of one menu as a tree with drag and drop. A flat table with a
 * parent select made the main gesture of the editor — hanging an item under
 * another one — a form field; here it is a drop, and the drag and drop rides
 * the sortable of MoonShine, exactly as the cards of the block editor do, so
 * the page grows no dependency of its own.
 *
 * Two levels is the whole depth. The interface stops offering a third one,
 * and the server refuses it anyway: the rule lives in the model of the
 * layouts package, see {@see CmsMenuItem::MAX_DEPTH}.
 *
 * An item is edited and added without leaving the menu: the form of the item
 * opens in a modal over the tree, and a save closes it and redraws the tree,
 * see {@see MenuItemFormPage::MODAL}.
 */
final class MenuTreeView extends MoonShineComponent
{
    public const FRAGMENT = 'cms-menu-tree';

    protected string $view = 'moonshine-cms::components.menu-tree';

    public function __construct(
        private readonly CmsMenu $menu,
        private readonly MenuResource $resource,
        private readonly PageContract $page,
    ) {
        parent::__construct();
    }

    public static function forMenu(CmsMenu $menu, MenuResource $resource, PageContract $page): self
    {
        return new self($menu, $resource, $page);
    }

    /**
     * @return list<\MoonShine\Contracts\AssetManager\AssetElementContract>
     */
    protected function assets(): array
    {
        $assets = [];
        $css = file_get_contents(__DIR__.'/../../../../resources/css/menu-tree.css');
        $js = file_get_contents(__DIR__.'/../../../../resources/js/cms-menu-tree.js');

        if ($css !== false) {
            $assets[] = InlineCss::make($css);
        }

        if ($js !== false) {
            $assets[] = InlineJs::make($js);
        }

        // The form of an item arrives in a dialog without a head of its own,
        // so the page it lands on carries what its link picker runs on.
        return [...$assets, ...EditorAssets::inline(), ...MediaAssets::inline()];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'nodes' => $this->nodes(),
            'fragmentEvent' => AlpineJs::event(JsEvent::FRAGMENT_UPDATED, self::FRAGMENT),
            'maxDepth' => CmsMenuItem::MAX_DEPTH,
            'addButton' => $this->modalButton(
                (string) __('moonshine-cms::menus.item_add'),
                MenuItemFormPage::modalName(),
                $this->itemUrl(),
            )->primary()->customAttributes(['data-cms-menu-add' => true]),
            // One sortable group per menu: an item never travels from the tree
            // of one menu into another, and the server refuses it anyway.
            'group' => 'cms-menu-items-'.$this->menu->id,
            // The address names the menu: a move is checked against it, so a
            // crafted identifier cannot renumber the items of another menu.
            'moveUrl' => $this->getCore()->getRouter()->getEndpoints()->method(
                'moveMenuItem',
                params: ['resourceItem' => $this->menu->id],
                page: $this->page,
                resource: $this->resource,
            ),
            // What the client renders on its own cannot be translated by
            // blade, the way the block editor hands its own words over.
            'labels' => ['requestFailed' => (string) __('moonshine-cms::editor.request_failed')],
        ];
    }

    /**
     * Read the way the page tree reads: one query, grouped by parent in
     * memory.
     *
     * @return list<MenuTreeNode>
     */
    private function nodes(): array
    {
        /** @var Collection<int, CmsMenuItem> $rows */
        $rows = CmsMenuItem::query()
            ->where('cms_menu_id', $this->menu->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $rows->groupBy(static fn (CmsMenuItem $item): int => $item->parent_id ?? 0);

        return $this->branch($byParent, 0, 0);
    }

    /**
     * @param  Collection<int, Collection<int, CmsMenuItem>>  $byParent
     * @return list<MenuTreeNode>
     */
    private function branch(Collection $byParent, int $parentId, int $depth): array
    {
        $nodes = [];

        foreach ($byParent->get($parentId, new Collection()) as $item) {
            $label = Translated::caption($item->label);

            $nodes[] = new MenuTreeNode(
                id: $item->id,
                label: $label !== '' ? $label : '#'.$item->id,
                target: $this->target($item),
                isActive: $item->is_active,
                depth: $depth,
                modal: MenuItemFormPage::modalName($item->id),
                editButton: $this->modalButton(
                    '',
                    MenuItemFormPage::modalName($item->id),
                    $this->itemUrl($item->id),
                    title: (string) __('moonshine-cms::menus.item_title_edit', ['label' => $label !== '' ? $label : '#'.$item->id]),
                )
                    ->icon('pencil')
                    ->customAttributes([
                        'data-cms-menu-edit' => $item->id,
                        'title' => (string) __('moonshine-cms::menus.item_edit'),
                        'aria-label' => (string) __('moonshine-cms::menus.item_edit'),
                    ]),
                addChildButton: $this->modalButton(
                    (string) __('moonshine-cms::menus.item_add_child'),
                    MenuItemFormPage::modalName(parent: $item->id),
                    $this->itemUrl(parent: $item->id),
                )->customAttributes(['data-cms-menu-add-child' => $item->id]),
                children: $this->branch($byParent, $item->id, $depth + 1),
            );
        }

        return $nodes;
    }

    /**
     * What the row says about the target. An item without one is a group
     * caption of a footer column and says so instead of looking broken.
     */
    private function target(CmsMenuItem $item): ?string
    {
        if (! $item->hasTarget()) {
            return null;
        }

        $link = $item->toLink();

        return $link === null
            ? (string) __('moonshine-cms::menus.item_link_unknown')
            : (string) __('moonshine-cms::links.kinds.'.$link->kind->value);
    }

    /**
     * A button that opens the form of an item in a dialog. The dialog fetches
     * the form when it opens, each time anew: a save redraws the tree and with
     * it every dialog, and one closed without saving forgets what was typed.
     */
    private function modalButton(string $caption, string $modal, string $url, ?string $title = null): ActionButtonContract
    {
        return ActionButton::make($caption, $url)
            ->async()
            ->inModal(
                title: $title ?? (string) __('moonshine-cms::menus.item_title_new'),
                content: '',
                name: $modal,
                builder: static fn (ModalContract $dialog): ModalContract => $dialog->wide()->alwaysLoad(),
            )
            ->class('btn-sm');
    }

    /**
     * The form of one item, or the form of a new one in this menu — under a
     * parent when the row offers «add a nested item» — as the dialog loads it:
     * the form alone, saving in place, see {@see MenuItemFormPage::MODAL}.
     */
    private function itemUrl(int|string|null $key = null, ?int $parent = null): string
    {
        $params = [
            'menu' => $this->menu->id,
            MenuItemFormPage::MODAL => 1,
            '_async_form' => 1,
        ];

        if ($parent !== null) {
            $params['parent'] = $parent;
        }

        return $this->getCore()
            ->getContainer(MenuItemResource::class)
            ->getFormPageUrl($key, params: $params, fragment: 'crud-form');
    }
}
