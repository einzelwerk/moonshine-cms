<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A grid with numbered pages, filters and an editable portion size.
 */
final class CatalogBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'catalog';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Каталог')
            ->params([
                EnumParam::make('city')->options(CityOptions::class)->nullable()->filter(),
                StringParam::make('month')->pattern('^\d{4}-(0[1-9]|1[0-2])$')->nullable()->filter(),
            ])
            ->pagination(mode: PaginationMode::PAGES, perPage: 5, maxPerPage: 20, editable: true)
            ->sort('event_date')
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return DynamicListBlockResource::class;
    }
}
