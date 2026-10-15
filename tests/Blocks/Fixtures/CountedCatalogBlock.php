<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A catalogue whose filter reads its values through {@see CountingCityOptions}.
 */
final class CountedCatalogBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'counted_catalog';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Каталог со счётчиком')
            ->params([EnumParam::make('city')->options(CountingCityOptions::class)->nullable()->filter()])
            ->pagination(mode: PaginationMode::PAGES, perPage: 5)
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return DynamicListBlockResource::class;
    }
}
