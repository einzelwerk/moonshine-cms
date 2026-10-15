<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuSlotResource;
use Ewk\MoonShineCms\Support\MenuSlotLabel;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;

/**
 * @extends FormPage<MenuSlotResource>
 */
class MenuSlotFormPage extends FormPage
{
    /**
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        return [
            Select::make(__('moonshine-cms::chrome.slot'), 'slot')
                ->options($this->slotOptions())
                ->required()
                ->hint(__('moonshine-cms::chrome.slot_hint'))
                ->native(),
            BelongsTo::make(
                __('moonshine-cms::menus.title'),
                'cmsMenu',
                static fn (CmsMenu $menu): string => Translated::caption($menu->getAttribute('name')) ?: $menu->code,
                MenuResource::class,
            )
                ->required()
                ->searchable(),
            Switcher::make(__('moonshine-cms::chrome.slot_enabled'), 'is_enabled')->default(true),
        ];
    }

    protected function rules(DataWrapperContract $item): array
    {
        return [
            'slot' => ['required', 'string', Rule::in(array_keys($this->slotOptions()))],
            'cms_menu_id' => ['required', 'integer', 'exists:cms_menus,id'],
        ];
    }

    /**
     * The closed dictionary of the installation: a slot is a place in the
     * markup of the frontend, so the list is the catalog and never a list of
     * its own. The places of the chrome only: a place the installation declared
     * for a record of its own belongs to that record, not to a header or a
     * footer.
     *
     * @return array<string, string>
     */
    private function slotOptions(): array
    {
        $options = [];

        foreach ($this->getCore()->getContainer(MenuSlotCatalog::class)->chromeCodes() as $code) {
            // A place is named in words: the code is the contract with the
            // markup of the frontend and says nothing to an editor.
            $options[$code] = MenuSlotLabel::of($code);
        }

        return $options;
    }
}
