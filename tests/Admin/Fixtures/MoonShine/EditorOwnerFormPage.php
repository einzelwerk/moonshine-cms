<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\MoonShine;

use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The owner form. The block editor is not on it: a card of the editor is a
 * form and HTML has no nested forms, so the editor has a page of its own and
 * the form only carries the switch to it.
 *
 * @extends FormPage<EditorOwnerResource>
 */
final class EditorOwnerFormPage extends FormPage
{
    /**
     * @return list<ComponentContract>
     */
    protected function topLayer(): array
    {
        $components = parent::topLayer();

        if ($this->getResource()->getItem() === null) {
            return $components;
        }

        return [$this->getResource()->blockEditorSwitcher(false), ...$components];
    }

    protected function fields(): iterable
    {
        return [
            Box::make([
                ID::make(),
                Text::make('Name', 'name'),
                Switcher::make('Active', 'is_active'),
            ]),
        ];
    }
}
