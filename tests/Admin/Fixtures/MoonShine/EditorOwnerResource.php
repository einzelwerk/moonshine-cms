<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\MoonShine;

use Ewk\MoonShineCms\Concerns\InteractsWithBlockEditor;
use Ewk\MoonShineCms\Contracts\EditsBlocksContract;
use Ewk\MoonShineCms\Tests\Fixtures\EditorOwner;
use MoonShine\Laravel\Resources\ModelResource;

/**
 * The resource a consuming application would write for a page: it owns the
 * blocks, mounts the editor commands and registers the editor page.
 *
 * @extends ModelResource<EditorOwner, EditorOwnerIndexPage, EditorOwnerFormPage, EditorOwnerFormPage>
 */
final class EditorOwnerResource extends ModelResource implements EditsBlocksContract
{
    use InteractsWithBlockEditor;

    protected string $model = EditorOwner::class;

    protected string $column = 'name';

    protected string $title = 'Owners';

    protected function pages(): array
    {
        return [
            EditorOwnerIndexPage::class,
            EditorOwnerFormPage::class,
            EditorOwnerBlocksPage::class,
        ];
    }
}
