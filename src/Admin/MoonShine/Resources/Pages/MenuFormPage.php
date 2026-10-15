<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Menus\MenuSourceRegistry;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\Actions\SaveMenu;
use Ewk\MoonShineCms\Concerns\DeletesFromTheForm;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Components\MenuTreeView;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\Support\ChromeCode;
use Ewk\MoonShineCms\Support\MenuSlotLabel;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Fragment;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\Support\Enums\HttpMethod;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The settings of a menu and the tree of its items.
 *
 * What an editor answers here is «how it is called», «where it is shown» and
 * «where its items come from». The places are the slots of the header and the
 * footer and the places the installation declared with their owner, the
 * footer of a letter for one, all named in words; the rows they stand for are
 * written by {@see \Ewk\MoonShineCms\Actions\SaveMenu}.
 *
 * @extends FormPage<MenuResource>
 */
class MenuFormPage extends FormPage
{
    use DeletesFromTheForm;

    /**
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        $fields = [
            $this->nameField()->switcher()->slugSource(),
            Text::make(__('moonshine-cms::fields.code'), 'code')
                ->nullable()
                ->customAttributes(['data-cms-slug-target' => 'code'])
                ->hint(__('moonshine-cms::menus.code_hint')),
            $this->slotsField(),
            // The mark that says this form asked about the places at all, see
            // SaveMenu::SUBMITTED: an empty multiple select sends nothing.
            Hidden::make(SaveMenu::SUBMITTED)->setValue('1')->canApply(static fn (): bool => false),
            Select::make(__('moonshine-cms::menus.source'), 'source')
                ->options($this->sourceOptions())
                ->default(CmsMenu::SOURCE_MANUAL)
                ->hint(__('moonshine-cms::menus.source_hint'))
                ->native(),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->default(true),
        ];

        $menu = $this->currentMenu();

        if ($menu !== null) {
            // Filling from a dictionary rewrites rows, so it needs a row to
            // rewrite; and it only makes sense where a dictionary is the
            // source. It asks before it rewrites and posts with the token of
            // the panel; the confirmation is a modal of the panel, teleported
            // out of this form, so no form ends up inside another.
            if ($this->sources()->has($menu->source)) {
                $fields[] = ActionButton::make(
                    __('moonshine-cms::menus.fill_from_source', [
                        'source' => $this->sources()->get($menu->source)->title(),
                    ]),
                    $this->fillUrl($menu),
                )
                    ->icon('arrow-path')
                    ->customAttributes(['data-cms-fill-source' => $menu->source])
                    ->async(HttpMethod::POST)
                    ->withConfirm(
                        content: (string) __('moonshine-cms::menus.fill_confirm'),
                        name: 'cms-menu-fill-'.$menu->id,
                    );
            }

            // The items are a tree with drag and drop, not a table with a
            // parent select: hanging an item under another one is the gesture
            // the editor repeats. It sits in a fragment, so a move redraws the
            // list without a page reload.
            $resource = $this->getResource();

            if ($resource instanceof MenuResource) {
                $fields[] = Fragment::make([MenuTreeView::forMenu($menu, $resource, $this)])
                    ->name(MenuTreeView::FRAGMENT);
            }
        }

        return [
            LocaleScope::wrap(
                $fields,
                stateKey: 'cms-menu-form',
                slugAuto: $menu === null,
            ),
        ];
    }

    public function getTitle(): string
    {
        $menu = $this->currentMenu();

        if ($menu === null) {
            return (string) __('moonshine-cms::menus.title_new');
        }

        $caption = Translated::caption($menu->getAttribute('name'));

        return (string) __('moonshine-cms::menus.title_edit', [
            'name' => $caption !== '' ? $caption : $menu->code,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs[$this->getRoute()] = $this->getTitle();

        return $breadcrumbs;
    }

    /**
     * @param  DataWrapperContract<CmsMenu>  $item
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'code' => ChromeCode::rules('cms_menus', $item->getKey()),
            ...$this->nameField()->rules(),
            'source' => ['required', 'string', Rule::in(array_keys($this->sourceOptions()))],
            'slots' => ['nullable', 'array'],
            'slots.*' => ['string', Rule::in(array_keys($this->slotOptions()))],
        ];
    }

    /**
     * The code behaves the way the address of a page does: it is built from the
     * name while the menu is created, stays visible and is never rewritten by a
     * rename afterwards.
     */
    public function prepareForValidation(): void
    {
        ChromeCode::derive(request(), $this->languages()->defaultLocale());
    }

    public function validationMessages(): array
    {
        return ChromeCode::messages();
    }

    /**
     * Where the menu is shown: the places of the header and the footer and the
     * places the installation declared, named in words. Several are allowed,
     * because one list of links is often both a column of the footer and the
     * legal row under it.
     *
     * The value is no column of the menu, so the field takes no part in the
     * apply of the panel: the rows of `cms_menu_slot_assignments` are written by
     * {@see \Ewk\MoonShineCms\Actions\SaveMenu}.
     */
    protected function slotsField(): ComponentContract
    {
        $chosen = $this->chosenSlots();

        $field = Select::make(__('moonshine-cms::menus.slots'), 'slots')
            ->options($this->slotOptions())
            ->multiple()
            ->nullable()
            ->hint(__($this->hasChrome()
                ? 'moonshine-cms::menus.slots_hint'
                : 'moonshine-cms::menus.slots_no_chrome'))
            ->onBeforeRender(static function (Select $select) use ($chosen): Select {
                $select->setValue($chosen);

                return $select;
            });

        return $field->canApply(static fn (): bool => false);
    }

    /**
     * @return array<string, string>
     */
    protected function slotOptions(): array
    {
        $options = [];

        foreach ($this->slots()->codes() as $code) {
            // A place an installation added through the configuration says
            // nothing about its owner, so it is filled on the form of the
            // header or the footer and is not offered here. A declared place
            // names its owner and is offered like a place of the chrome.
            if (! $this->slots()->isAssignable($code)) {
                continue;
            }

            $options[$code] = MenuSlotLabel::of($code);
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function chosenSlots(): array
    {
        $menu = $this->currentMenu();

        if ($menu === null) {
            return [];
        }

        $chosen = [];

        foreach (CmsMenuSlotAssignment::query()->where('cms_menu_id', $menu->id)->orderBy('id')->get() as $assignment) {
            $chosen[] = $assignment->slot;
        }

        return $chosen;
    }

    /**
     * Whether there is anywhere to put the menu at all. A declared place
     * counts once its owner exists, even before a header or a footer does; a
     * declared place whose owner is not there yet is nowhere to put it either.
     */
    private function hasChrome(): bool
    {
        foreach ($this->slots()->definitions() as $definition) {
            if ($definition->owner() !== null) {
                return true;
            }
        }

        return CmsHeader::query()->exists()
            || CmsFooter::query()->exists();
    }

    /**
     * The manual source plus every dictionary the installation registered.
     *
     * @return array<string, string>
     */
    private function sourceOptions(): array
    {
        $options = [CmsMenu::SOURCE_MANUAL => (string) __('moonshine-cms::menus.source_manual')];

        foreach ($this->sources()->all() as $code => $source) {
            $options[$code] = $source->title();
        }

        return $options;
    }

    /**
     * The address of «fill from the source». It answers a POST only, so the
     * items of a menu cannot be rewritten by an address that a page somewhere
     * manages to load, see {@see MenuResource::fillFromSource()}.
     */
    private function fillUrl(CmsMenu $menu): string
    {
        return $this->getCore()->getRouter()->getEndpoints()->method(
            'fillFromSource',
            params: ['resourceItem' => $menu->id],
            page: $this,
            resource: $this->getResource(),
        );
    }

    private function nameField(): TranslatableText
    {
        return TranslatableText::make(__('moonshine-cms::fields.name'), 'name')->required();
    }

    private function currentMenu(): ?CmsMenu
    {
        $item = $this->getResource()?->getItem();

        return $item instanceof CmsMenu ? $item : null;
    }

    private function sources(): MenuSourceRegistry
    {
        return $this->getCore()->getContainer(MenuSourceRegistry::class);
    }

    private function slots(): MenuSlotCatalog
    {
        return $this->getCore()->getContainer(MenuSlotCatalog::class);
    }

    private function languages(): LanguageCatalog
    {
        return $this->getCore()->getContainer(LanguageCatalog::class);
    }
}
