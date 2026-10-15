<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Http\Resources\BlockResource;
use Ewk\Cms\Blocks\Params\EnumParam;
use Ewk\Cms\Blocks\Params\IntegerParam;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Params\StringParam;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A dynamic block: one filter, one numbered pagination. Two of them on one page
 * prove that the parameter sets do not leak between the anchors.
 */
final class ListingBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'listing';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Список')
            ->category('content')
            ->fields([
                TextField::make('title')->label('Заголовок')->translatable(),
            ])
            ->params([
                StringParam::make('city')->label('Город')->nullable()->filter(),
                IntegerParam::make('year')->label('Год')->min(2000)->max(2100)->nullable(),
                // A set computed per request out of the entity of the page.
                EnumParam::make('pick')->label('Выбор')->options(SubjectOptions::class)->nullable()->filter(),
            ])
            ->pagination(PaginationMode::PAGES, perPage: 2, maxPerPage: 5)
            ->placement(root: '*', parents: '*', shared: false);
    }

    /**
     * @return class-string<BlockResource>
     */
    public function resource(): string
    {
        return ListingBlockResource::class;
    }
}
