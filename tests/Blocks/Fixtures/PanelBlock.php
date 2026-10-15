<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\IntegerParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A block that takes parameters and shows no list of its own: dynamic through
 * its parameters alone, without any pagination.
 */
final class PanelBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'panel';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Панель фильтров')
            ->params([
                EnumParam::make('city')->options(['berlin' => 'Берлин'])->nullable()->filter(),
                IntegerParam::make('radius')->min(1)->max(50)->default(10),
            ])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
