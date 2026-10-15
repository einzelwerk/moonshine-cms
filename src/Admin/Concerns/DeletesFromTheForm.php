<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Concerns;

use MoonShine\Contracts\UI\ActionButtonContract;
use MoonShine\Support\ListOf;

/**
 * Where the deletion of a record sits on a form.
 *
 * The panel puts it above the fields, red and wide, so that it is the first
 * thing an editor meets on a form he opened to change a word. It moves down
 * next to the save, where the rest of the form actions are, and keeps the
 * confirmation modal the button always had.
 *
 * Nothing else changes: it is still the delete button of the resource, with
 * its own permissions and its own redirect.
 *
 * @phpstan-require-extends \MoonShine\Crud\Pages\FormPage
 */
trait DeletesFromTheForm
{
    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function buttons(): ListOf
    {
        return new ListOf(ActionButtonContract::class, []);
    }

    /**
     * @return ListOf<ActionButtonContract>
     */
    protected function formButtons(): ListOf
    {
        $resource = $this->getResource();

        if ($resource === null || ! $this->isItemExists()) {
            return new ListOf(ActionButtonContract::class, []);
        }

        return new ListOf(ActionButtonContract::class, [
            $resource->getDeleteButton(
                redirectAfterDelete: $resource->getRedirectAfterDelete(),
                isAsync: false,
            ),
        ]);
    }
}
