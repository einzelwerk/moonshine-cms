<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\Continuation;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * The second transport of `more`: the list is appended on the client through
 * the endpoint of the block, and the address of the page never changes.
 */
final class CursorFeedBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'cursor_feed';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Курсорная подборка')
            ->pagination(
                mode: PaginationMode::MORE,
                perPage: 10,
                continuation: Continuation::ENDPOINT,
            )
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return DynamicListBlockResource::class;
    }
}
