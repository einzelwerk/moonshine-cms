<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\Switcher;

/**
 * The list of headers and the list of footers, which look the same: the
 * caption an editor picks by and whether the record is on. The code lives on
 * the form, next to the name it is built from.
 *
 * @extends IndexPage<\Ewk\MoonShineCms\MoonShine\Resources\HeaderResource|\Ewk\MoonShineCms\MoonShine\Resources\FooterResource>
 */
class ChromeIndexPage extends IndexPage
{
    /**
     * @return iterable<FieldContract>
     */
    protected function fields(): iterable
    {
        return [
            TranslatableText::make(__('moonshine-cms::fields.name'), 'name'),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->updateOnPreview()->sortable(),
        ];
    }
}
