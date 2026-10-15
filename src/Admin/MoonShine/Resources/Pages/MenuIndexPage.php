<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Menus\MenuSourceRegistry;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\Support\MenuSlotLabel;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The menus as an editor reads them: the caption, the places the menu takes in
 * the header and the footer, where its items come from and whether it is on.
 * The code is on the form, where it is edited, and not a column here: a list
 * of codes says nothing about which menu is which.
 *
 * @extends IndexPage<MenuResource>
 */
class MenuIndexPage extends IndexPage
{
    /** @var array<int, list<string>>|null */
    private ?array $placements = null;

    /**
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            TranslatableText::make(__('moonshine-cms::fields.name'), 'name'),
            Text::make(
                __('moonshine-cms::menus.slots'),
                'slots',
                fn (CmsMenu $menu): string => $this->places($menu),
            ),
            Text::make(
                __('moonshine-cms::menus.source'),
                'source',
                fn (CmsMenu $menu): string => $this->source($menu),
            ),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->updateOnPreview()->sortable(),
        ];
    }

    /**
     * Where the menu is shown, in words. A menu nobody placed says nothing
     * rather than showing an empty column of dashes.
     *
     * The places of every menu are read in one query and kept for the whole
     * rendering: a column that asks the storage once per row turns a list of
     * ten menus into eleven queries.
     */
    private function places(CmsMenu $menu): string
    {
        return implode(', ', $this->placements()[$menu->id] ?? []);
    }

    /**
     * @return array<int, list<string>>
     */
    private function placements(): array
    {
        if ($this->placements !== null) {
            return $this->placements;
        }

        $places = [];

        foreach (CmsMenuSlotAssignment::query()->orderBy('id')->get() as $assignment) {
            $places[$assignment->cms_menu_id][] = MenuSlotLabel::of($assignment->slot);
        }

        return $this->placements = $places;
    }

    private function source(CmsMenu $menu): string
    {
        $sources = $this->getCore()->getContainer(MenuSourceRegistry::class);

        return $sources->has($menu->source)
            ? $sources->get($menu->source)->title()
            : (string) __('moonshine-cms::menus.source_manual');
    }
}
