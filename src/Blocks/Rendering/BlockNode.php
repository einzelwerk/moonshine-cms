<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Rendering;

use Ewk\Cms\Blocks\Models\CmsBlock;

/**
 * One assembled node of the block tree: the row plus its children by slot.
 *
 * Children live beside the block, never inside its content: a uniform traversal
 * of the tree is what makes one recursive envelope, one recursive frontend type
 * and one anchor collection possible.
 */
final readonly class BlockNode
{
    /**
     * @param  array<string, list<BlockNode>>  $children  children by slot name
     */
    public function __construct(
        public CmsBlock $block,
        public array $children = [],
    ) {
    }
}
