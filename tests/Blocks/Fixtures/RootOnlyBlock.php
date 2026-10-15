<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A block type that declares no allowed container: it lives on the root level
 * of a page and nowhere else.
 */
final class RootOnlyBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'test_root_only';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Только корень')
            ->fields([])
            ->placement(root: '*', parents: [], shared: false);
    }
}
