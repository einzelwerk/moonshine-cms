<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Media\MoonShine\Library\PickerModal;
use Ewk\MoonShineCms\Actions\SaveMenuItem;
use Ewk\MoonShineCms\Adapters\FieldAdapter;
use Ewk\MoonShineCms\Concerns\DeletesFromTheForm;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Components\MenuTreeView;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\MenuItemResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\Support\Translated;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Contracts\UI\FormBuilderContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Collections\Fields;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;

/**
 * One entry of a menu.
 *
 * The caption lives on the item and not inside the link — in a menu it is
 * usually shorter than the title of the target — and the target is the typed
 * link of the blocks package, rendered by the very same widget the block
 * editor uses. A ready address is never stored.
 *
 * The form carries the language switch of the CMS: the caption is shown in
 * the language it selects, in the place the form declares it.
 *
 * The parent select offers root items only. Two levels is the whole depth,
 * and the model of the package refuses a third one; {@see SaveMenuItem}
 * turns that refusal into a message on this field instead of repeating the
 * check. Where an item hangs is usually changed by dragging it in the tree
 * of the menu form; this select is the other way in, and the one a freshly
 * created item has.
 *
 * The form is reached from that tree, so a new item learns its menu from the
 * address (`?menu=`) and its parent from the row that offered «add a nested
 * item» (`?parent=`).
 *
 * The tree opens it in a modal, the form alone loaded into the dialog (see
 * {@see self::MODAL}); the page of its own stays for a direct address.
 *
 * @extends FormPage<MenuItemResource>
 */
class MenuItemFormPage extends FormPage
{
    use DeletesFromTheForm;

    /**
     * The mark of the address the tree opens the form in a modal with. The
     * form then saves without leaving the menu: it stays on the page, closes
     * its dialog and redraws the tree, instead of the redirect a save on the
     * page of its own makes.
     */
    public const string MODAL = '_cms_modal';

    /**
     * The name of the dialog the tree opens an item in, or a new item in, at
     * the root or under a parent. The tree draws the dialog and the form
     * closes it by this name, so neither reads it from the address.
     */
    public static function modalName(?int $item = null, ?int $parent = null): string
    {
        return match (true) {
            $item !== null => 'cms-menu-item-'.$item,
            $parent !== null => 'cms-menu-item-new-'.$parent,
            default => 'cms-menu-item-new',
        };
    }

    /**
     * @return list<ComponentContract>
     */
    protected function topLayer(): array
    {
        $components = parent::topLayer();
        $menuId = $this->menuId();

        if ($menuId === null) {
            return $components;
        }

        $back = ActionButton::make(
            (string) __('moonshine-cms::menus.item_back'),
            $this->getCore()->getContainer(MenuResource::class)->getFormPageUrl($menuId),
        )->icon('arrow-left');

        return [$back, ...$components];
    }

    /**
     * The caption, the target, the place in the tree and the activity. There is
     * no order field: the order of the items is the order of the tree. The icon
     * and the variant are gone from the form too: no set of either is declared
     * anywhere, and the frontend draws neither, so the inputs asked an editor
     * for a code nobody reads. The columns stay, and what a dictionary wrote
     * into them stays with them.
     *
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        $fields = [
            $this->labelField()->switcher(),
            $this->linkField(),
            Select::make(__('moonshine-cms::menus.item_parent'), 'parent_id')
                ->options($this->parentOptions())
                ->default($this->requestedParent())
                ->nullable()
                ->customAttributes(['placeholder' => (string) __('moonshine-cms::menus.item_parent_none')])
                ->hint(__('moonshine-cms::menus.item_parent_hint'))
                ->native(),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->default(true),
        ];

        // Which menu a new item belongs to travels in the address, the way
        // the relation field of the panel carries it in a hidden input. An
        // item that exists already knows its menu and never rewrites it.
        $menuId = $this->menuId();

        if (! $this->itemExists() && $menuId !== null) {
            $fields[] = Hidden::make('cms_menu_id')->setValue($menuId);
        }

        if ($this->inModal()) {
            // The resource sends a save back to the menu form, which the page
            // the dialog stands on already is.
            $fields[] = Hidden::make('_without-redirect')->setValue('1')->canApply(static fn (): bool => false);

            // The menu form under the dialog has drawn the media library the
            // link picker opens already; a second copy would answer its event
            // too.
            PickerModal::drawnByHost();
        }

        return [LocaleScope::wrap($fields, stateKey: 'cms-menu-item-form')];
    }

    /**
     * In the dialog of the tree a save closes the dialog and redraws the tree,
     * see {@see self::MODAL}.
     */
    protected function modifyFormComponent(FormBuilderContract $component): FormBuilderContract
    {
        if (! $this->inModal()) {
            return $component;
        }

        $item = $this->getResource()?->getItem();
        $modal = $item instanceof CmsMenuItem && $item->exists
            ? self::modalName($item->id)
            : self::modalName(parent: $this->requestedParent());

        return $component->async(events: [
            AlpineJs::event(JsEvent::MODAL_TOGGLED, $modal),
            AlpineJs::event(JsEvent::FRAGMENT_UPDATED, MenuTreeView::FRAGMENT),
        ]);
    }

    public function getTitle(): string
    {
        $item = $this->getResource()?->getItem();

        if (! $item instanceof CmsMenuItem || ! $item->exists) {
            return (string) __('moonshine-cms::menus.item_title_new');
        }

        $label = Translated::caption($item->label);

        return (string) __('moonshine-cms::menus.item_title_edit', [
            'label' => $label !== '' ? $label : '#'.$item->id,
        ]);
    }

    /**
     * The trail an editor walked: the menus, the menu this item belongs to,
     * and the item. The hidden list of every item of every menu is nowhere an
     * editor is meant to arrive at, so it is not offered as a step back.
     *
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $menuId = $this->menuId();
        $menu = $menuId === null ? null : CmsMenu::query()->find($menuId);

        if ($menu instanceof CmsMenu) {
            $menus = $this->getCore()->getContainer(MenuResource::class);
            $caption = Translated::caption($menu->name);

            $breadcrumbs = [
                $menus->getIndexPageUrl() => $menus->getTitle(),
                $menus->getFormPageUrl($menu->id) => (string) __('moonshine-cms::menus.title_edit', [
                    'name' => $caption !== '' ? $caption : $menu->code,
                ]),
            ];
        }

        $breadcrumbs[$this->getRoute()] = $this->getTitle();

        return $breadcrumbs;
    }

    /**
     * @param  DataWrapperContract<CmsMenuItem>  $item
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            ...$this->labelField()->rules(),
            // Absent on an update: the item keeps the menu it was created in.
            'cms_menu_id' => ['sometimes', 'required', 'integer', 'exists:cms_menus,id'],
            'parent_id' => ['nullable', 'integer', 'exists:cms_menu_items,id'],
        ];
    }

    private function labelField(): TranslatableText
    {
        return TranslatableText::make(__('moonshine-cms::menus.item_label'), 'label')->required();
    }

    /**
     * The link is a nested value of one json column, so it is detached from
     * the generic apply of the panel and written by {@see SaveMenuItem}.
     */
    private function linkField(): FieldContract
    {
        $field = $this->getCore()->getContainer(FieldAdapter::class)->adapt(
            LinkField::make('link')->label((string) __('moonshine-cms::menus.item_link')),
            prefix: '',
        );

        Fields::make([$field])
            ->onlyFields(withWrappers: true, withApplyWrappers: true)
            ->each(static fn (FieldContract $nested): FieldContract => $nested->canApply(static fn (): bool => false));

        return $field;
    }

    /**
     * @return array<int, string>
     */
    private function parentOptions(): array
    {
        $menuId = $this->menuId();

        if ($menuId === null) {
            return [];
        }

        $item = $this->getResource()?->getItem();

        $query = CmsMenuItem::query()
            ->where('cms_menu_id', $menuId)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($item instanceof CmsMenuItem && $item->exists) {
            $query->whereKeyNot($item->getKey());
        }

        $options = [];

        foreach ($query->get() as $candidate) {
            $label = Translated::caption($candidate->label);
            $options[$candidate->id] = $label !== '' ? $label : '#'.$candidate->id;
        }

        return $options;
    }

    /**
     * «Add a nested item» on the tree opens the form with the parent chosen.
     */
    private function requestedParent(): ?int
    {
        $parent = request()->query('parent');

        return is_numeric($parent) ? (int) $parent : null;
    }

    private function inModal(): bool
    {
        return request()->boolean(self::MODAL);
    }

    private function itemExists(): bool
    {
        $item = $this->getResource()?->getItem();

        return $item instanceof CmsMenuItem && $item->exists;
    }

    /**
     * The menu this item belongs to: its own when it exists, and the menu the
     * address names while it is being created.
     */
    private function menuId(): ?int
    {
        $item = $this->getResource()?->getItem();

        if ($item instanceof CmsMenuItem && $item->exists) {
            return $item->cms_menu_id;
        }

        foreach ([request()->input('menu'), request()->input('cms_menu_id'), request()->input('_parentId')] as $candidate) {
            if (is_numeric($candidate)) {
                return (int) $candidate;
            }
        }

        return null;
    }
}
