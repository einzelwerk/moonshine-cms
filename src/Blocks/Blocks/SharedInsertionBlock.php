<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * An insertion of a library entry: an ordinary row with type `shared` and a
 * foreign key to the shared block. It has no content of its own.
 *
 * The position, the parent, the slot, the order and the anchor belong to the
 * insertion; the content, the variant and the children belong to the original.
 * Manual activity belongs to both and combines through AND.
 *
 * The renderer substitutes the tree of the original in its place, so the
 * frontend never learns that a block is shared and draws it with the same
 * component. The uuid of the envelope stays the uuid of the insertion.
 *
 * A reference to a shared block inside a shared block is forbidden, which
 * removes cycles altogether without a depth check.
 */
final class SharedInsertionBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'shared';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title((string) __('cms-blocks::blocks.shared.title'))
            ->category('library')
            ->fields([])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
