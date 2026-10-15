<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use MoonShine\Contracts\UI\ActionButtonContract;

/**
 * One item of a menu as the panel draws it.
 */
final readonly class MenuTreeNode
{
    /**
     * @param  list<MenuTreeNode>  $children
     */
    public function __construct(
        public int $id,
        public string $label,
        /** The caption of the target, or null for a group caption without one. */
        public ?string $target,
        public bool $isActive,
        public int $depth,
        /** The dialog the form of the item opens in; the caption opens it too. */
        public string $modal,
        public ActionButtonContract $editButton,
        public ActionButtonContract $addChildButton,
        public array $children,
    ) {
    }
}
