<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\MoonShineCms\MoonShine\Pages\BlockEditorPage;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;
use Ewk\MoonShineCms\Support\ModelKey;
use Illuminate\Database\Eloquent\Model;

/**
 * The block editor of one library entry. A shared block is an owner of blocks
 * like any other, so the editor, the statuses and the publish button are the
 * very same page the pages use and nothing of it is written twice.
 *
 * @extends BlockEditorPage<SharedBlockResource>
 */
class SharedBlockBlocksPage extends BlockEditorPage
{
    /**
     * The one rule that makes this editor different from the editor of a page:
     * exactly one root block. It is said here and not only enforced, because an
     * editor who reads it never runs into the refusal at all.
     */
    protected function editorHint(): ?string
    {
        return (string) __('moonshine-cms::shared.editor_hint');
    }

    protected function ownerCaption(Model $owner): string
    {
        if (! $owner instanceof CmsSharedBlock) {
            return (string) ModelKey::of($owner);
        }

        return $owner->name !== '' ? $owner->name : $owner->code;
    }
}
