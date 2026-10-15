<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\MoonShineCms\MoonShine\Resources\LayoutResource;
use Ewk\MoonShineCms\Support\Translated;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The shells of the installation: a closed set declared in code, so the list
 * has no «create» and no deletion, only the settings of what exists. The codes
 * are not columns of this table either: an editor reads the caption, the
 * frontend reads the code.
 *
 * @extends IndexPage<LayoutResource>
 */
class LayoutIndexPage extends IndexPage
{
    /**
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            Text::make(
                __('moonshine-cms::fields.name'),
                'name',
                static fn (CmsLayout $layout): string => Translated::caption($layout->getAttribute('name')) ?: $layout->code,
            ),
            Text::make(
                __('moonshine-cms::chrome.header'),
                'cmsHeader',
                static fn (CmsLayout $layout): string => Translated::caption($layout->cmsHeader?->getAttribute('name')),
            ),
            Text::make(
                __('moonshine-cms::chrome.footer'),
                'cmsFooter',
                static fn (CmsLayout $layout): string => Translated::caption($layout->cmsFooter?->getAttribute('name')),
            ),
            Switcher::make(__('moonshine-cms::chrome.is_default'), 'is_default')->updateOnPreview(),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->updateOnPreview(),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    protected function topLayer(): array
    {
        return [
            Alert::make(icon: 'information-circle')
                ->content((string) __('moonshine-cms::chrome.layout_catalog_hint')),
            ...parent::topLayer(),
        ];
    }
}
