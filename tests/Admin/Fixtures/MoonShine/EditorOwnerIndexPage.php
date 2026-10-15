<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\MoonShine;

use MoonShine\Laravel\Pages\Crud\IndexPage;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Text;

/**
 * @extends IndexPage<EditorOwnerResource>
 */
final class EditorOwnerIndexPage extends IndexPage
{
    protected function fields(): iterable
    {
        return [
            ID::make(),
            Text::make('Name', 'name'),
        ];
    }
}
