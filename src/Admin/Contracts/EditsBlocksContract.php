<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Contracts;

use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\UI\ComponentContract;

/**
 * What a resource offers once it mounts the block editor, see
 * {@see \Ewk\MoonShineCms\Concerns\InteractsWithBlockEditor}, which implements
 * all of it.
 *
 * The editor lives on a page of its own next to the form of the item, so both
 * pages need the address of the other one and the same switcher between them.
 */
interface EditsBlocksContract
{
    /**
     * The address of the block editor of one item.
     */
    public function blockEditorPageUrl(int|string $key): string;

    /**
     * «Settings | Blocks»: the same pair of buttons on both pages, the current
     * one highlighted.
     */
    public function blockEditorSwitcher(bool $editorActive): ComponentContract;

    /**
     * The owner of the blocks: the item of this resource.
     */
    public function blockEditorOwner(): Model;
}
