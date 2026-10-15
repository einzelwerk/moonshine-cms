<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A filter whose set of values is empty right now.
 */
final class EmptySetBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'empty_set';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Фильтр без значений')
            ->params([EnumParam::make('city')->options(EmptyOptions::class)->nullable()->filter()])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
