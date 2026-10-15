<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuSlotResource;
use Ewk\MoonShineCms\Support\MenuSlotLabel;
use Ewk\MoonShineCms\Support\Translated;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Fields\Relationships\BelongsTo;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<MenuSlotResource>
 */
class MenuSlotIndexPage extends IndexPage
{
    /**
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Text::make(
                __('moonshine-cms::chrome.slot'),
                'slot',
                static fn (CmsMenuSlotAssignment $assignment): string => MenuSlotLabel::of($assignment->slot),
            )->badge()->sortable(),
            // The menu is named the way an editor named it: the code of a menu
            // is the contract with the frontend and belongs on its own form.
            BelongsTo::make(
                __('moonshine-cms::menus.title'),
                'cmsMenu',
                static fn (CmsMenu $menu): string => Translated::caption($menu->getAttribute('name')) ?: $menu->code,
                MenuResource::class,
            ),
            Switcher::make(__('moonshine-cms::chrome.slot_enabled'), 'is_enabled')->updateOnPreview(),
        ];
    }
}
