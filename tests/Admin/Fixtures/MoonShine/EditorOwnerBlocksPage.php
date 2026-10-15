<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\MoonShine;

use Ewk\MoonShineCms\MoonShine\Pages\BlockEditorPage;
use Illuminate\Database\Eloquent\Model;

/**
 * The block editor of one owner, on a page of its own — what a consuming
 * application registers next to the form of the item.
 *
 * @extends BlockEditorPage<EditorOwnerResource>
 */
final class EditorOwnerBlocksPage extends BlockEditorPage
{
    protected function ownerCaption(Model $owner): string
    {
        $name = $owner->getAttribute('name');

        return \is_string($name) && $name !== '' ? $name : (string) $owner->getKey();
    }
}
