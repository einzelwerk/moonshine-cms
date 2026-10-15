<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\DateParam;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A growing slice behind a "show more" button, continued through the address
 * of the page, with a ceiling of its own below the one of the config.
 */
final class FeedBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'feed';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Подборка')
            ->params([
                DateParam::make('date')->nullable()->filter(),
            ])
            ->pagination(mode: PaginationMode::MORE, perPage: 12, maxShown: 48)
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return DynamicListBlockResource::class;
    }
}
