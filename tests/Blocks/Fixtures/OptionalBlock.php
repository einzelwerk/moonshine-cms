<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A block with nothing to show while its caption is empty.
 */
final class OptionalBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'optional';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Необязательный блок')
            ->fields([TextField::make('caption')->label('Подпись')])
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return OptionalBlockResource::class;
    }
}
